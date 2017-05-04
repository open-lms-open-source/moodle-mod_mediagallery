M.mod_mediagallery = M.mod_mediagallery || {};
M.mod_mediagallery.dragdrop = {

    CSS : {
        CONTAINER : '.gallery_items',
        ITEMS : '.gallery_items .item',
        CONTROLCONTAINER : '.controls',
        HANDLE : '.controls :first-child',
        HANDLELINK : '.controls .move'
    },

    init : function() {
        var MOVEICON = {
            pix: "i/move_2d",
            component: 'moodle'
        };

        // Static Vars.
        var goingUp = false, lastX = 0, lastY = 0;

        var list = Y.Node.all(this.CSS.ITEMS);
        list.each(function(v) {
            var CSS = M.mod_mediagallery.dragdrop.CSS;
            // Replace move link and image with move_2d image.
            var imagenode = Y.Node.create('<img class="smallicon move action-icon" title="' + M.str.moodle.move + '"/>');
            imagenode.setAttribute('src', M.util.image_url(MOVEICON.pix, MOVEICON.component));
            imagenode.addClass('cursor');
            v.one(CSS.CONTROLCONTAINER).prepend(imagenode);

            var dd = new Y.DD.Drag({
                node: v,
                target: {
                    padding: '0 0 0 20'
                }
            }).plug(Y.Plugin.DDProxy, {
                moveOnEnd: false
            }).plug(Y.Plugin.DDConstrained, {
                constrain2node: CSS.CONTAINER
            });
            dd.addHandle(CSS.HANDLE);
        });

        Y.DD.DDM.on('drag:start', function(e) {
            e.preventDefault();
            // Get our drag object.
            var drag = e.target;
            // Set some styles here.
            drag.get('node').setStyle('opacity', '.25');
            drag.get('dragNode').addClass('mod_mediagallery item');
            drag.get('dragNode').set('innerHTML', drag.get('node').get('innerHTML'));
            drag.get('dragNode').setStyles({
                opacity: '.5',
                borderColor: drag.get('node').getStyle('borderColor'),
                backgroundColor: drag.get('node').getStyle('backgroundColor')
            });
        });

        Y.DD.DDM.on('drag:end', function(e) {
            var drag = e.target;
            // Put our styles back.
            drag.get('node').setStyles({
                visibility: '',
                opacity: '1'
            });
            M.mod_mediagallery.dragdrop.save();
        });

        Y.DD.DDM.on('drag:drag', function(e) {
            // Get the last y point.
            var x = e.target.lastXY[0];
            var y = e.target.lastXY[1];
            if (x < lastX) {
                // We are going up.
                goingUp = true;
            } else {
                // We are going down.
                goingUp = false;
            }
            // Cache for next check.
            lastX = x;
            lastY = y;

        });

        Y.DD.DDM.on('drop:over', function(e) {
            // Get a reference to our drag and drop nodes.
            var drag = e.drag.get('node'),
                drop = e.drop.get('node');

            var list = Y.all(M.mod_mediagallery.dragdrop.CSS.ITEMS);
            if (list.indexOf(drop) < list.indexOf(drag)) {
                goingUp = true;
            } else {
                goingUp = false;
            }

            if (drop.hasClass('item')) {
                // Are we not going up?
                if (!goingUp) {
                    drop = drop.get('nextSibling');
                }
                // Add the node to this list.
                e.drop.get('node').get('parentNode').insertBefore(drag, drop);
                // Resize this nodes shim, so we can drop on it later.
                e.drop.sizeShim();
            }
        });

        Y.DD.DDM.on('drag:drophit', function(e) {
            var drop = e.drop.get('node'),
                drag = e.drag.get('node');

            // If we are not on an li, we must have been dropped on a ul.
            if (!drop.hasClass('item')) {
                if (!drop.contains(drag)) {
                    drop.appendChild(drag);
                }
            }
        });
    },

    save : function() {
        var sortorder = Y.one(this.CSS.CONTAINER).get('children').getData('id');
        var params = {
            sesskey : M.cfg.sesskey,
            data : sortorder,
            "class" : 'gallery',
            m : M.mod_mediagallery.base.mid,
            id : M.mod_mediagallery.base.gallery,
            action : 'sortorder'
        };
        Y.io(M.cfg.wwwroot + '/mod/mediagallery/rest.php', {
            method: 'POST',
            data: build_querystring(params),
            context: this
        });
    }
};
