M.mod_assign = {};

M.mod_assign.init_tree = function(Y, expand_all, htmlid) {
    var treeElement = Y.one('#'+htmlid);
    if (treeElement) {
        Y.use('yui2-treeview', 'node-event-simulate', function(Y) {
            var tree = new Y.YUI2.widget.TreeView(htmlid);

            tree.subscribe("clickEvent", function(node, event) {
                // We want normal clicking which redirects to url.
                return false;
            });

            tree.subscribe("enterKeyPressed", function(node) {
                // We want keyboard activation to trigger a click on the first link.
                Y.one(node.getContentEl()).one('a').simulate('click');
                return false;
            });

            if (expand_all) {
                tree.expandAll();
            }
            tree.render();
        });
    }
};

M.mod_assign.init_grading_table = function(Y) {
    Y.use('node', function(Y) {
        checkboxes = Y.all('td.c0 input');
        checkboxes.each(function(node) {
            node.on('change', function(e) {
                rowelement = e.currentTarget.get('parentNode').get('parentNode');
                if (e.currentTarget.get('checked')) {
                    rowelement.removeClass('unselectedrow');
                    rowelement.addClass('selectedrow');
                } else {
                    rowelement.removeClass('selectedrow');
                    rowelement.addClass('unselectedrow');
                }
            });

            rowelement = node.get('parentNode').get('parentNode');
            if (node.get('checked')) {
                rowelement.removeClass('unselectedrow');
                rowelement.addClass('selectedrow');
            } else {
                rowelement.removeClass('selectedrow');
                rowelement.addClass('unselectedrow');
            }
        });

        var selectall = Y.one('th.c0 input');
        if (selectall) {
            selectall.on('change', function(e) {
                if (e.currentTarget.get('checked')) {
                    checkboxes = Y.all('td.c0 input[type="checkbox"]');
                    checkboxes.each(function(node) {
                        rowelement = node.get('parentNode').get('parentNode');
                        node.set('checked', true);
                        rowelement.removeClass('unselectedrow');
                        rowelement.addClass('selectedrow');
                    });
                } else {
                    checkboxes = Y.all('td.c0 input[type="checkbox"]');
                    checkboxes.each(function(node) {
                        rowelement = node.get('parentNode').get('parentNode');
                        node.set('checked', false);
                        rowelement.removeClass('selectedrow');
                        rowelement.addClass('unselectedrow');
                    });
                }
            });
        }

        var batchform = Y.one('form.gradingbatchoperationsform');
        if (batchform) {
            batchform.on('submit', function(e) {
                checkboxes = Y.all('td.c0 input');
                var selectedusers = [];
                checkboxes.each(function(node) {
                    if (node.get('checked')) {
                        selectedusers[selectedusers.length] = node.get('value');
                    }
                });

                operation = Y.one('#id_operation');
                usersinput = Y.one('input.selectedusers');
                usersinput.set('value', selectedusers.join(','));
                if (selectedusers.length == 0) {
                    alert(M.util.get_string('nousersselected', 'assign'));
                    e.preventDefault();
                } else {
                    action = operation.get('value');
                    prefix = 'plugingradingbatchoperation_';
                    if (action.indexOf(prefix) == 0) {
                        pluginaction = action.substr(prefix.length);
                        plugin = pluginaction.split('_')[0];
                        action = pluginaction.substr(plugin.length + 1);
                        confirmmessage = M.util.get_string('batchoperationconfirm' + action, 'assignfeedback_' + plugin);
                    } else {
                        confirmmessage = M.util.get_string('batchoperationconfirm' + operation.get('value'), 'assign');
                    }
                    if (!confirm(confirmmessage)) {
                        e.preventDefault();
                    }
                }
            });
        }

        var quickgrade = Y.all('.gradingtable .quickgrade');
        quickgrade.each(function(quick) {
            quick.on('change', function(e) {
                this.get('parentNode').addClass('quickgrademodified');
            });
        });
    });
};

M.mod_assign.init_grading_options = function(Y) {
    Y.use('node', function(Y) {
        var paginationelement = Y.one('#id_perpage');
        paginationelement.on('change', function(e) {
            Y.one('form.gradingoptionsform').submit();
        });
        var filterelement = Y.one('#id_filter');
        if (filterelement) {
            filterelement.on('change', function(e) {
                Y.one('form.gradingoptionsform').submit();
            });
        }
        var markerfilterelement = Y.one('#id_markerfilter');
        if (markerfilterelement) {
            markerfilterelement.on('change', function(e) {
                Y.one('form.gradingoptionsform').submit();
            });
        }
        var workflowfilterelement = Y.one('#id_workflowfilter');
        if (workflowfilterelement) {
            workflowfilterelement.on('change', function(e) {
                Y.one('form.gradingoptionsform').submit();
            });
        }
        var quickgradingelement = Y.one('#id_quickgrading');
        if (quickgradingelement) {
            quickgradingelement.on('change', function(e) {
                Y.one('form.gradingoptionsform').submit();
            });
        }
        var showonlyactiveenrolelement = Y.one('#id_showonlyactiveenrol');
        if (showonlyactiveenrolelement) {
            showonlyactiveenrolelement.on('change', function(e) {
            Y.one('form.gradingoptionsform').submit();
            });
        }
        var downloadasfolderselement = Y.one('#id_downloadasfolders');
        if (downloadasfolderselement) {
            downloadasfolderselement.on('change', function(e) {
                Y.one('form.gradingoptionsform').submit();
            });
        }
    });
};

M.mod_assign.init_plugin_summary = function(Y, subtype, type, submissionid) {
    var suffix = subtype + '_' + type + '_' + submissionid;
    var classname = 'contract_' + suffix;
    var contract = Y.one('.' + classname);
    if (contract) {
        contract.on('click', function(e) {
            e.preventDefault();
            var link = e.currentTarget || e.target;
            var linkclasses = link.getAttribute('class').split(' ');
            var thissuffix = '';
            for (var i = 0; i < linkclasses.length; i++) {
                classname = linkclasses[i];
                if (classname.indexOf('contract_') == 0) {
                    thissuffix = classname.substr(9);
                }
            }
            var fullclassname = 'full_' + thissuffix;
            var full = Y.one('.' + fullclassname);
            if (full) {
                full.hide(false);
            }
            var summaryclassname = 'summary_' + thissuffix;
            var summary = Y.one('.' + summaryclassname);
            if (summary) {
                summary.show(false);
                summary.one('a.expand_' + thissuffix).focus();
            }
        });
    }
    classname = 'expand_' + suffix;
    var expand = Y.one('.' + classname);

    var full = Y.one('.full_' + suffix);
    if (full) {
        full.hide(false);
        full.toggleClass('hidefull');
    }
    if (expand) {
        expand.on('click', function(e) {
            e.preventDefault();
            var link = e.currentTarget || e.target;
            var linkclasses = link.getAttribute('class').split(' ');
            var thissuffix = '';
            for (var i = 0; i < linkclasses.length; i++) {
                classname = linkclasses[i];
                if (classname.indexOf('expand_') == 0) {
                    thissuffix = classname.substr(7);
                }
            }
            var summaryclassname = 'summary_' + thissuffix;
            var summary = Y.one('.' + summaryclassname);
            if (summary) {
                summary.hide(false);
            }
            var fullclassname = 'full_' + thissuffix;
            full = Y.one('.' + fullclassname);
            if (full) {
                full.show(false);
                full.one('a.contract_' + thissuffix).focus();
            }
        });
    }
};

// Code for updating the countdown timer that is used on timed assignments.
M.mod_assign.timer = {
    // YUI object.
    Y: null,

    // Timestamp at which time runs out, according to the student's computer's clock.
    endtime: 0,

    // This records the id of the timeout that updates the clock periodically,
    // so we can cancel.
    timeoutid: null,

    /**
     * @param Y the YUI object
     * @param start, the timer starting time, in seconds.
     */
    init: function(Y, start) {
        M.mod_assign.timer.Y = Y;
        M.mod_assign.timer.endtime = M.pageloadstarttime.getTime() + start*1000;
        M.mod_assign.timer.update();
        Y.one('#assign-timer').setStyle('display', 'block');
    },

    /**
     * Stop the timer, if it is running.
     */
    stop: function(e) {
        if (M.mod_assign.timer.timeoutid) {
            clearTimeout(M.mod_assign.timer.timeoutid);
        }
    },

    /**
     * Function to convert a number between 0 and 99 to a two-digit string.
     */
    two_digit: function(num) {
        if (num < 10) {
            return '0' + num;
        } else {
            return num;
        }
    },

    // Function to update the clock with the current time left.
    update: function() {
        var Y = M.mod_assign.timer.Y;
        var secondsleft = Math.floor((M.mod_assign.timer.endtime - new Date().getTime())/1000);

        // If time has expired, set the hidden form field that says time has expired and submit
        if (secondsleft < 0) {
            M.mod_assign.timer.stop(null);
            return;
        }

        // If time has nearly expired, change the colour.
        if (secondsleft < 100) {
            Y.one('#assign-timer').removeClass('timeleft' + (secondsleft + 2))
                .removeClass('timeleft' + (secondsleft + 1))
                .addClass('timeleft' + secondsleft);
        }

        // Update the time display.
        var hours = Math.floor(secondsleft/3600);
        secondsleft -= hours*3600;
        var minutes = Math.floor(secondsleft/60);
        secondsleft -= minutes*60;
        var seconds = secondsleft;
        Y.one('#assign-time-left').setContent(hours + ':' +
            M.mod_assign.timer.two_digit(minutes) + ':' +
            M.mod_assign.timer.two_digit(seconds));

        // Arrange for this method to be called again soon.
        M.mod_assign.timer.timeoutid = setTimeout(M.mod_assign.timer.update, 100);
    }
};