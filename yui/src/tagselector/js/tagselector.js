M.mod_mediagallery = M.mod_mediagallery || {};
M.mod_mediagallery.tagselector = {

    init: function (field, tags, useajax) {
        // For docs, see: http://yuilibrary.com/yui/docs/autocomplete/ac-tagging.html.
        var node = Y.one('#' + field);
        if (tags && typeof tags === 'object') {
            // Convert object into array.
            var tags2 = [];
            for (var key in tags) {
                tags2.push(tags[key]);
            }
            tags = tags2;
        }

        if (!node) {
            return;
        }

        var source = tags;
        if (useajax) {
            source = M.cfg.wwwroot + '/mod/mediagallery/tagajax.php?insttype=collection&q={query}&sesskey=' + M.cfg.sesskey;
        }

        node.plug(Y.Plugin.AutoComplete, {
            allowTrailingDelimiter: true,
            minQueryLength: 1,
            queryDelay: 0,
            queryDelimiter: ',',
            source: source,
            width: 'auto',
            resultHighlighter: 'startsWith',
            resultFilters: ['startsWith', function (query, results) {
                // Split the current input value into an array based on comma delimiters.
                var selected = node.get('value').split(/\s*,\s*/);

                // Convert the array into a hash for faster lookups.
                selected = Y.Array.hash(selected);

                // Filter out any results that are already selected, then return the
                // array of filtered results.
                return Y.Array.filter(results, function (result) {
                    return !selected.hasOwnProperty(result.text);
                });
            }]
        });

        // After a tag is selected, send an empty query to update the list of tags.
        node.ac.after('select', function () {
            // Send the query on the next tick to ensure that the input node's blur
            // handler doesn't hide the result list right after we show it.
            setTimeout(function () {
                node.ac.sendRequest('');
                node.ac.show();
            }, 1);
        });
    }

};
