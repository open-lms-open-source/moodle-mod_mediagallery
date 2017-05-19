# Media collection activity for Moodle

[![Build Status](https://travis-ci.org/netspotau/moodle-mod_mediagallery.svg?branch=master)](https://travis-ci.org/netspotau/moodle-mod_mediagallery)
[![License](https://poser.pugx.org/netspotau/moodle-mod_mediagallery/license)](https://packagist.org/packages/netspotau/moodle-mod_mediagallery)

This plugin allows instructors/teachers to create a space for students to submit "galleries". These galleries can be based on images, audio or video. The galleries themselves can be used as part of gradeable assignments when used in conjuction with the media collection submission module (http://github.com/netspotau/moodle-assignsubmission_mediagallery).

This activity was written by Adam Olley \<adam.olley@netspot.com.au\> for the University of New South Wales (http://www.unsw.edu.au).

## Install
### Using Moodle
You can install the plugin from the Moodle plugin repository from within your Moodle installation.
### Using a downloaded zip file
You can download a zip of this module from: https://github.com/netspotau/moodle-mod_mediagallery/zipball/master  
Unzip it to your mod/ folder and rename the extracted folder to 'mediagallery'.
### Using Git
To install using git, run the following command from the root of your moodle installation:  
git clone git://github.com/netspotau/moodle-mod_mediagallery.git mod/mediagallery  

Then add mod/mediagallery to your gitignore.

## Companion plugins
### Assignment submission plugin
Allows teachers to link an assignment to a collection activity. When students submit a gallery for assessment, the gallery becomes read only after the duedate.

Repo: http://github.com/netspotau/moodle-assignsubmission_mediagallery

### Media gallery search block
Allows users to add a block to their course which lets users search for galleries within any Media collections in the course.

Repo: http://github.com/netspotau/moodle-block_mediagallery

### Filter
When used in conjunction with the TinyMCE plugin, this allows users to insert carousel views to a gallery in their course.

Repo: http://github.com/netspotau/moodle-filter_mediagallery

### TinyMCE button
Provides a button that can be added to TinyMCE for inserting galleries into TinyMCE editable areas.

Repo: http://github.com/netspotau/moodle-tinymce_mediagallery

## Credits
Media collection was developed by NetSpot Pty Ltd (http://www.netspot.com.au) for the University of New South Wales (http://www.unsw.edu.au).

Code: Adam Olley \<adam.olley@blackboard.com\>  
Concept: UNSW (http://www.unsw.edu.au)  
Design: UNSW (http://www.unsw.edu.au) & Mark Bailye \<mark.bailye@netspot.com.au\>  
Testing: UNSW (http://www.unsw.edu.au)  
