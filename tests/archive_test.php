<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for adding items in bulk from a zip archive.
 *
 * @package   mod_mediagallery
 * @copyright 2026 Open LMS
 * @author    Adam Olley <adam.olley@openlms.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mediagallery;

/**
 * Tests that zip archives are checked before they are extracted.
 *
 * @copyright 2026 Open LMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_mediagallery\item::create_from_archive
 * @covers    \mod_mediagallery\item::get_archive_entries
 */
final class archive_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;

    /** @var \stdClass A student in the course, who owns the gallery. */
    private $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($this->student);
    }

    public function test_only_media_files_become_items(): void {
        [$collection, $gallery] = $this->create_gallery();
        $archive = $this->create_archive([
            'one.png' => $this->get_image(),
            'photos/two.png' => $this->get_image(),
            'song.mp3' => 'audio',
            'notes.html' => '<script>alert(1)</script>',
            'shell.php' => '<?php',
            'empty.jpg' => '',
        ]);

        item::create_from_archive($gallery, $archive, new \stdClass());

        $this->assertEqualsCanonicalizing(['one.png', 'two.png', 'song.mp3'], $this->get_item_filenames($collection, $gallery));
        $this->assertFalse(get_file_storage()->file_exists_by_hash($archive->get_pathnamehash()));
    }

    public function test_items_stop_at_the_gallery_limit(): void {
        [$collection, $gallery] = $this->create_gallery(['maxitems' => 3]);
        $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_item(['galleryid' => $gallery->id]);
        $image = $this->get_image();
        $archive = $this->create_archive(['a.png' => $image, 'b.png' => $image, 'c.png' => $image, 'd.png' => $image]);

        item::create_from_archive($gallery, $archive, new \stdClass());

        $this->assertEquals(['a.png', 'b.png'], $this->get_item_filenames($collection, $gallery));
    }

    public function test_full_gallery_is_refused(): void {
        [, $gallery] = $this->create_gallery(['maxitems' => 1]);
        $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_item(['galleryid' => $gallery->id]);

        $this->expectExceptionMessage(get_string('errortoomanyitems', 'mediagallery', 1));
        item::create_from_archive($gallery, $this->create_archive(['a.jpg' => 'a']), new \stdClass());
    }

    public function test_too_many_files_are_refused_before_extracting(): void {
        global $DB;
        [, $gallery] = $this->create_gallery();
        $files = [];
        for ($i = 0; $i <= item::BULK_MAX_FILES; $i++) {
            $files["image{$i}.jpg"] = "image {$i}";
        }

        try {
            item::create_from_archive($gallery, $this->create_archive($files), new \stdClass());
            $this->fail('An archive over the file limit was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('errorbulktoomanyfiles', $e->errorcode);
        }
        $this->assertEquals(0, $DB->count_records('mediagallery_item', ['galleryid' => $gallery->id]));
        $this->assertEquals(0, $DB->count_records('files', ['component' => 'mod_mediagallery']));
    }

    public function test_files_over_the_size_limit_are_refused(): void {
        global $DB;
        [, $gallery] = $this->create_gallery(['maxbytes' => 1000]);
        $archive = $this->create_archive(['small.jpg' => 'small', 'big.jpg' => str_repeat('x', 2000)]);

        try {
            item::create_from_archive($gallery, $archive, new \stdClass());
            $this->fail('An archive with a file over the size limit was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('errorbulkfiletoolarge', $e->errorcode);
        }
        $this->assertEquals(0, $DB->count_records('mediagallery_item', ['galleryid' => $gallery->id]));
    }

    public function test_archive_without_media_is_refused(): void {
        [, $gallery] = $this->create_gallery();

        $this->expectExceptionMessage(get_string('errorbulknomedia', 'mediagallery'));
        item::create_from_archive($gallery, $this->create_archive(['readme.txt' => 'text']), new \stdClass());
    }

    public function test_file_that_is_not_an_archive_is_refused(): void {
        [, $gallery] = $this->create_gallery();
        $notzip = get_file_storage()->create_file_from_string($this->get_draft_record('fake.zip'), 'not a zip');

        try {
            item::create_from_archive($gallery, $notzip, new \stdClass());
            $this->fail('A file that is not a zip archive was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('errorbulkinvalid', $e->errorcode);
        }
        // Core reports the unreadable archive through debugging().
        $this->assertDebuggingCalled();
    }

    /**
     * Create a collection and a gallery in it owned by the student.
     *
     * @param array $settings Extra collection settings.
     * @return array The collection's activity record and the gallery.
     */
    private function create_gallery(array $settings = []): array {
        $collection = $this->getDataGenerator()->create_module('mediagallery',
            $settings + ['course' => $this->course->id, 'colltype' => 'contributed']);
        $gallery = $this->getDataGenerator()->get_plugin_generator('mod_mediagallery')->create_gallery([
            'instanceid' => $collection->id,
            'name' => 'Gallery',
            'userid' => $this->student->id,
        ]);
        return [$collection, $gallery];
    }

    /**
     * Get the contents of a real image, so thumbnails can be made from it.
     *
     * @return string
     */
    private function get_image(): string {
        global $CFG;
        return file_get_contents($CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
    }

    /**
     * Create a zip archive in the student's draft area.
     *
     * @param string[] $files File contents, keyed by their path in the archive.
     * @return \stored_file
     */
    private function create_archive(array $files): \stored_file {
        $tozip = [];
        foreach ($files as $path => $content) {
            $tozip[$path] = [$content];
        }
        $record = $this->get_draft_record('media.zip');
        return get_file_packer('application/zip')->archive_to_storage($tozip, $record['contextid'], $record['component'],
            $record['filearea'], $record['itemid'], $record['filepath'], $record['filename']);
    }

    /**
     * Get the file record for a file in the student's draft area.
     *
     * @param string $filename
     * @return array
     */
    private function get_draft_record(string $filename): array {
        return [
            'contextid' => \context_user::instance($this->student->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => $filename,
        ];
    }

    /**
     * Get the names of the files attached to a gallery's items, in the order the items were made.
     *
     * @param \stdClass $collection
     * @param gallery $gallery
     * @return string[]
     */
    private function get_item_filenames(\stdClass $collection, gallery $gallery): array {
        global $DB;
        $contextid = \context_module::instance($collection->cmid)->id;
        $names = [];
        foreach ($DB->get_fieldset_select('mediagallery_item', 'id', 'galleryid = ? ORDER BY id', [$gallery->id]) as $itemid) {
            foreach (get_file_storage()->get_area_files($contextid, 'mod_mediagallery', 'item', $itemid, 'id', false) as $file) {
                $names[] = $file->get_filename();
            }
        }
        return $names;
    }
}
