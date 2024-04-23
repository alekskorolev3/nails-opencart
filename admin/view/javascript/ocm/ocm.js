//developer and copyright opencartmart.com
//version 1.0.1
;(function(window){
    var _methods = [];
    var auto_config = {
        category: {
            url: 'index.php?route=catalog/category/autocomplete',
            id: 'category_id'
        },
        product: {
            url: 'index.php?route=catalog/product/autocomplete',
            id: 'product_id'
        },
        manufacturer_rule: {
            url: 'index.php?route=catalog/manufacturer/autocomplete',
            id: 'manufacturer_id'
        },
        option: {
            url: 'index.php?route=' + _ocm.path + '/getOption',
            id: 'option_value_id'
        },
        location_rule: {
            url: 'index.php?route=' + _ocm.path + '/getLocation',
            id: 'name'
        },
        customer: {
            url: 'index.php?route=' + _ocm.path + '/getCustomer',
            id: 'customer_id'
        },
        /* OCM Browser  */
        manufacturer_id: {
             url: 'index.php?route=catalog/manufacturer/autocomplete',
             id: 'manufacturer_id'
        },
        category_id: {
            url: 'index.php?route=catalog/category/autocomplete',
            id: 'category_id'
        },
        manufacturer: {
             url: 'index.php?route=catalog/manufacturer/autocomplete',
             id: 'manufacturer_id'
        },
        /* Other modules */
        category_rule: {
            url: 'index.php?route=catalog/category/autocomplete',
            id: 'category_id'
        },
        product_rule: {
            url: 'index.php?route=catalog/product/autocomplete',
            id: 'product_id'
        }
    };
    var ocm_complete = {
        debounce: null,
        source: function source(request, response) {
            var attr = $(this).attr('attr');
            var config = ocm_complete.getConfig(attr);
            if (typeof config.url == 'function') {
                response(config.url.call(null, request));
            } else {
                clearTimeout(ocm_complete.debounce);
                ocm_complete.debounce = setTimeout(fetchData, 200); 
            }
            function fetchData() {
                var url = config.url + '&filter_name=' + request;
                ocm_action.doAjax(url, null, function(json) {
                    response($.map(json, function(item) {
                        return {
                            label: item['name'],
                            value: item[config.id]
                        }
                    }));
                });
            }
        },
        select: function select(item) {
            var attr = $(this).attr('attr');
            var config = ocm_complete.getConfig(attr);
            if (config && attr == config.id) {
                var container = $(this).closest('div[ocm-attr]');
                $(this).val(item['label']);
                container.find('input.' + config.id).val(item['value']);
            } else {
                var container = $(this).closest('div[ocm-attr]').find('.ocm-autofill-box');
                var name = container.attr('name');
                $(this).val('');
                $('.ocm-autofill-item[value="' + item['value'] + '"]', container).remove();
                container.append('<div value="' + item['value'] + '" class="ocm-autofill-item"><i class="fa fa-minus-circle"></i> ' + item['label'] + '<input type="hidden" name="' + name + '" value="' + item['value'] + '" /></div>');
            }
        },
        getConfig: function(key) {
            var _default = {
               url: 'index.php?route=' + _ocm.path + '/' + key,
               id: 'id'
            };
            var config = auto_config[key];
            return config || _default;
        },
        addConfig: function(key, config) {
            auto_config[key] = config;
        }
    };
    var ocm_action = {
        onEnd: $.noop,
        extension: _ocm.name,
        loaderStatus: false,
        url: 'index.php?route=' + _ocm.path,
        showLoader: function() {
            if (this.loaderStatus) {
                return;
            }
            $('body').append('<div class="global-waiting">Processing...</div>');
            $('.global-waiting').css({top:'50%', left:'50%', marginTop:'-40px', marginLeft:'-75px'});
            this.loaderStatus = true;
        },
        hideLoader: function() {
            $('div.global-waiting').remove();
            this.loaderStatus = false;
        },
        grabData: function() {
            _methods = [];
            $('#method-list > li > a').each(function(i) {
                var tab_id = $(this).attr('rel');
                if (!tab_id) return;
                var data = $('#ocm-method-' + tab_id + ' :input').serialize() + '&tab_id=' + tab_id + '&sort_order=' + i;
                _methods.push(data);
            });
            return this;
        },
        saveGeneral: function() {
            var data = $('#tab-global :input').serialize();
            ocm_action.doAjax('save_general', data);
        },
        saveMethod: function() {
            var data = _methods.pop();
            if(data) {
                ocm_action.doAjax('quick_save', data);
            }
        },
        deleteMethod: function(tab_id) {
             var data = {
                tab_id: tab_id
            };
            ocm_action.doAjax('delete', data);
        },
        doAjax: function(action, data, fn) {
            this.showLoader();
            var url = /\//.test(action) ? action : ocm_action.url + '/' + action;
            var type = data ? 'POST' : 'GET';
            var _req = {
                url: url + '&' + _ocm.token,
                dataType: 'json',
                type: type,
                success: function(json) {
                    if (json['error']) {
                        alert(json['error']);
                        return;
                    }
                    if (_methods.length > 0) {
                        ocm_action.saveMethod();
                        return;
                    } else {
                        ocm_action.onEnd.call(null);
                    }
                    ocm_action.hideLoader();
                    if (fn) {
                        fn.call(null,json);
                    }
                }
            };
            if (data) {
                _req.data = data;
                if (data.constructor === FormData) {
                    _req.processData = false;
                    _req.contentType = false;
                }
            }
            $.ajax(_req);
        }
    };

    var ocm_util = {
        interpolate: function (str, data) {
            var regex = /{(\w+)}/gm,
                match,
                _str = str;
            while ((match = regex.exec(str)) !== null) {
                var value = data[match[1]];
                if (typeof value == 'undefined') value = '';
                _str = _str.replace(new RegExp(match[0], 'g'), value);
            }
            return _str;
        },
        get_in: function(needle, haystack, needle_key) {
            var item;
            for (var i = 0; i < haystack.length; i++) {
                if (needle == haystack[i][needle_key]) {
                    item = haystack[i];
                    break;
                }
            }
            return item;
        },
        in_dom_array: function(needle, nodes, attr) {
            var found = false;
            nodes.each(function(i) {
                if (needle == $(this).attr(attr)) {
                    found = true;
                }
            });
            return found;
        }
    };

    function OCMModal(params) {
        var _self = this,
            onButtonAction,
            close,
            loader = '<div class="ocm-modal-loader"><i class="fa fa-spinner fa-spin"></i></div>';
        this.setParams = function setParams(params) {
            var defaults = {
                width: 500,
                title: false,
                btns: [],
                onClose: $.noop,
                showClose: true,
                maxHeight: 300,
                modal: false,
            };
            this.params = Object.assign({}, defaults, (params || {}));
            this.getDom();
            return this;
        };
        this.updateParams = function(params) {
            for (var key in params) {
                this.params[key] = params[key];
            }
            this.getDom();
            return this;
        };
        this.onButtonAction = function onButtonAction(e) {
            if ($(e.target).find('i').hasClass('fa-spinner')) {
                return;
            }
            var btn = this.params.btns[parseInt($(e.target).attr('rel'))];
            btn.fn.call(null);
        };
        this.showButtonLoader = function buttonLoader(index) {
            var btn = $('#' + this.id).find('.modal-footer button')[index];
            if (btn) {
                $(btn).prepend('<i class="fa fa-spinner fa-spin"></i>');
            }
        };
        this.hideButtonLoader = function hideButtonLoader() {
            $('#' + this.id).find('.modal-footer button i').remove();
        };
        this.close = function close() {
            $('#' + this.id).modal('hide');
            this.params.onClose.call(null);
        };
        this.show = function show() {
            $('#' + this.id).remove();
            $('body').append(this.popup);
            $('#' + this.id).modal('show');
            $('#' + this.id + ' .modal-footer button').click(onButtonAction);
            $('#' + this.id + ' .ocm-modal-close').click(close);
            return this;
        };
        this.setContent = function setContent(html, scrollTop) {
            $('#' + this.id + ' .ocm-modal-content').html(html);
            if (scrollTop) {
                $('#' + this.id + ' .modal-body').scrollTop(0);
            }
            return this;
        };
        this.getContent = function getContent() {
            return $('#' + this.id + ' .ocm-modal-content').html().replace(loader, '');
        };
        this.getDom = function getDom() {
            var popup = '', 
                attr = '',
                maxCSS = this.params['maxHeight'] ? 'style="max-height: ' + this.params.maxHeight + 'px; overflow:auto;"' : '';
            if (this.params.modal) {
                attr += 'data-backdrop="static" data-keyboard="false"';
            }
            popup = '<div class="modal fade ocm-modal" id="' + this.id +'" role="dialog" ' + attr + '>';
            popup += '  <div class="modal-dialog modal-dialog-centered" style="width: ' + this.params.width + 'px;">';
            popup += '  <div class="modal-content">';
            if (this.params.title || this.params.showClose) {
                popup += '  <div class="modal-header">';
                if (this.params.title) {
                    popup += '  <h4 style="display:inline-block" class="modal-title">' + this.params.title + '</h4>';
                }
                if (this.params.showClose) {
                    popup += '  <button type="button" class="close ocm-modal-close">&times;</button>';
                }
                popup += '  </div>';
            }
            popup += '  <div class="modal-body ocm-modal-content" ' + maxCSS + '>';
            popup += loader;
            popup += '  </div>';
            if (this.params.btns.length > 0) {
                popup += '  <div class="modal-footer">';
                for (var i = 0; i < this.params.btns.length; i++) {
                    var btn = this.params.btns[i];
                    var btn_type = btn.type || 'primary';
                    var btn_ttile = btn.title || 'Submit';
                    popup += ' <button type="button" rel="' + i + '" class="btn btn-' + btn_type + '">' + btn_ttile + '</button>';
                }
                popup += '  </div>';
            }
            popup += '  </div>'
            popup += ' </div>'
            popup += '</div>';
            this.popup = popup;
        }
        onButtonAction = this.onButtonAction.bind(this);
        close = this.close.bind(this);
        this.id = 'ocm-modal' + Math.floor(Math.random() * 100) + Math.floor(Math.random() * 100);
        this.setParams(params);
    }

    function OCMBrowser() {
        var _self = this;
        var fields = {
            category: [
                {
                    title: 'ID',
                    value: 'category_id',
                },
                {
                    title: 'Name',
                    value: 'name',
                }
            ],
            product: [
                {
                    title: 'ID',
                    value: 'product_id',
                },
                {
                    title: 'Name',
                    value: 'name',
                },
                {
                    title: 'Price',
                    value: 'price',
                }
            ]
        };

        var category_filter = '<div class="ocm-browser-filter">';
        category_filter += '   <input type="text" name="filter[keyword]" value="" placeholder="Enter keyword" class="form-control ocm-browser-keyword" />';
        category_filter += '<button class="btn btn-primary ocm-browser-btn" name="ocm-browser-btn" type="button"><i class="fa fa-circle-o-notch fa-spin loading"></i>&nbsp;Search</button>'
        category_filter += '  </div>';

        var product_fitler = '<div class="ocm-browser-filter browser-product">';
        product_fitler += '<div class="ocm-browser-autofill ocm-visible" ocm-attr="manufacturer_id">';
        product_fitler += '<input type="text" attr="manufacturer_id" value="" placeholder="Search By Manufacturer" class="form-control" />';
        product_fitler += '<input type="hidden" class="manufacturer_id" name="filter[manufacturer_id]" value="0" />';
        product_fitler += '</div>';
        product_fitler += '<div class="ocm-browser-autofill ocm-visible" ocm-attr="category_id">';
        product_fitler += '   <input type="text" attr="category_id" value="" placeholder="Search By Category" class="form-control"/>';
        product_fitler += '<input type="hidden" class="category_id" name="filter[category_id]" value="0" />';
        product_fitler += '</div>';
        product_fitler += '<div class="ocm-browser-field">';
        product_fitler += '<select name="filter[field]" class="form-control">'
        product_fitler +=   '<option value="name">Search By Name</option>'
        product_fitler +=   '<option value="model">Search By Model</option>'
        product_fitler +=   '<option value="sku">Search By SKU</option>'
        product_fitler +=   '<option value="jan">Search By JAN</option>'
        product_fitler += '</select>';
        product_fitler += '</div>';
        product_fitler += '<input type="text" name="filter[keyword]" value="" placeholder="Enter keyword" class="form-control ocm-browser-keyword" />';
        product_fitler += '<button class="btn btn-primary ocm-browser-btn" name="ocm-browser-btn" type="button"><i class="fa fa-circle-o-notch fa-spin loading"></i>&nbsp;Search</button>'
        product_fitler += '</div>';

        var filter = {
            category: category_filter,
            product: product_fitler
        }
        
        var browser = '';
        browser += '  <div class="ocm-browser-content">';
        browser += '__FILTER__';
        browser += '<table class="table table-bordered table-hover">';
        browser += '   <thead>';
        browser += '     <tr>';
        browser += '      <td style="width: 1px;" class="text-center"><input type="checkbox" onclick="$(\'input[name*=ocm_selected]\').prop(\'checked\', this.checked);" /></td>';

        browser += '__COLS__';
        browser += '    </tr>';
        browser += '  </thead>';
        browser += '  <tbody>';
        browser += '  <tr><td align="center" colspan="4"><i class="fa fa-spinner fa-spin" style="font-size:24px"></i></td></tr>';
        browser += '  </tbody>';
        browser += ' </table>';
        browser += '</div>';
        this.result = [];
        this.requestData = function requestData() {
            var data;
            var action = this.type == 'category' ? 'index.php?route=catalog/category/autocomplete' : 'getProducts';
            if (this.type == 'category') {
                action += '&filter_name=' + $('.ocm-browser-keyword').val();
            } else {
                if ($('.ocm-browser-filter [attr="manufacturer_id"]').val() == '') {
                    $('.ocm-browser-filter input.manufacturer_id').val('');
                }
                if ($('.ocm-browser-filter [attr="category_id"]').val() == '') {
                    $('.ocm-browser-filter input.category_id').val('');
                }
                data = $('.ocm-browser-filter [name^="filter"]:input');
            }
            ocm_action.doAjax(action, data, onDataLoaded);
            $('.ocm-browser-btn i').show();
        };
        this.chooseItems = function chooseItems() {
            var data = $('.ocm-browser-content tbody').find(':input:checked');
            this.params.fn.call(null, this.type, data, this.result);
            this.modal.close();
        };
        this.show = function show(params) {
            this.params = params;
            this.type = this.params.type || 'category';
            this.cols = fields[this.type];
            this.id = this.type == 'category' ? 'category_id' : 'product_id';
            var _col_html = '';
            for (var i = 0; i < this.cols.length; i++) {
                var col = this.cols[i];
                _col_html += ' <td class="text-center">' + col.title + '</td>';
            }
            var modal_html = browser.replace('__COLS__', _col_html);
            modal_html = modal_html.replace('__FILTER__', filter[this.type]);

            this.modal.updateParams({title : (this.type == 'category' ? 'Category' : 'Product') + ' Browser'});
            this.modal.show();
            this.modal.setContent(modal_html);
            this.requestData();
            $('.ocm-browser-btn').click(requestData);
            $('.ocm-browser-autofill input').autocomplete(ocm_complete);
        };
        var requestData = this.requestData.bind(this);
        var chooseItems = this.chooseItems.bind(this);
        this.modal = new OCMModal({
            width: 550,
            maxHeight: 300,
            title: ' Browser',
            btns: [
                {
                    title: 'Choose Selected',
                    fn: chooseItems
                }
            ]
        });
        function onDataLoaded(json) {
            var _html = '';
            _self.result = json;
            json.forEach(function(item) {
                _html += '<tr>'
                _html += '  <td style="width: 1px;" class="text-center"><input type="checkbox" name="ocm_selected[]" value="' + item[_self.id] + '" /></td>';
                for (var i = 0; i < _self.cols.length; i++) {
                    var col = _self.cols[i];
                    _html += ' <td class="text-left">' + item[col.value] + '</td>';
                }
                _html += '</tr>';
            });

            if (!_html) {
                _html = '<tr><td colspan="4" class="text-center">No data is found </td></tr>';
            } else if (_self.type == 'category') {
                _html += '<tr><td colspan="4" class="text-left"><label><input type="checkbox" value="1" name="inc_child">&nbsp; Include sub-categories as well </label> </td></tr>';
            }
            $('.ocm-browser-content tbody').html(_html);
            $('.ocm-browser-btn i').hide();
        }
    }
    window.ocm = {
        complete: ocm_complete,
        action: ocm_action,
        util: ocm_util,
        browser: new OCMBrowser(),
        modal: new OCMModal()
    };
})(window);

/* Common function */
function getNextTab() {
    var next_tab = $('#ocm-container').find('div.ocm-method').length;
    next_tab = parseInt(next_tab) + 1;
    while ($('#ocm-method-' + next_tab).length != 0) {
        next_tab++;
    }
    return next_tab;
}
function copyMethod(tabId, input_domain) {
    var no_of_tab = getNextTab();
    var tab_item = $('#ocm-method-'+tabId).clone();
    var tab_html = '<div id="ocm-method-'+no_of_tab+'" class="tab-pane ocm-method">'+tab_item.html()+'</div>';
    tab_html = tab_html.replace(new RegExp(input_domain + '\\[([a-z_]+)\\]', 'igm'), input_domain + '[$1]');
    tab_html = tab_html.replace(/_(\d+)/g, '_'+no_of_tab); 
    $('#ocm-container').append(tab_html);
    addToMethodList(no_of_tab, $('#method-list a[rel="' + tabId +'"]').html());
      
    var inputs_text = $('#ocm-method-'+tabId+' input[type="text"]');
    var inputs_text_new = $('#ocm-method-'+no_of_tab+' input[type="text"]');
      
    var inputs_checkboxes = $('#ocm-method-'+tabId+' input[type="checkbox"]');
    var inputs_checkboxes_new = $('#ocm-method-'+no_of_tab+' input[type="checkbox"]');
      
    var inputs_selects = $('#ocm-method-'+tabId+' select');
    var inputs_selects_new = $('#ocm-method-'+no_of_tab+' select');
      
    inputs_text.each(function(index) {
        inputs_text_new.eq(index).val($(this).val());
    });
    inputs_selects.each(function(index) {
        inputs_selects_new.eq(index).val($(this).val());
    });
    inputs_checkboxes.each(function(index) {
        if ($(this).prop('checked')) {
            inputs_checkboxes_new.eq(index).prop('checked','checked');
        } else {
            inputs_checkboxes_new.eq(index).removeAttr('checked');
        }
    });
    return no_of_tab;
 }

function debugBrowser() {
    var debugInternal;
    ocm.modal.updateParams({
        modal: true,
        title: 'Live Debugger',
        onClose: function() {
           clearInterval(debugInternal);
        }
    });
    ocm.modal.show();
    debugInternal = setInterval(function() {
        ocm.action.doAjax('fetchDebug', [], function(json){
            var current_log = ocm.modal.getContent();
            current_log = current_log.replace(/<div\s+class="text-(success|danger)">.*?<\/div>/gi, '');
            var log = json.log;
            if (!log) {
                log = '<div class="text-success">Please try to checkout on the site. Waiting for logs...</div>';
            }
            if (log) {
                ocm.modal.setContent(log + current_log, true);
            }
        });
    }, 2000);
}

function enableDragDrop() {
     var dragged,
         container;
     $("#form-ocm").on('drag', '.draggable-container', function(e) { 
         e.preventDefault();
     }); 
     $("#form-ocm").on('dragstart', '.draggable-container', function(e) {
         if (e.target.nodeName.toLowerCase() == 'input') {
            e.preventDefault();
            return;
         }
         var $this = $(e.target).closest('.draggable');
         dragged = $this[0];
         container = $(e.target).closest('.draggable-container')[0];
         $this.removeClass('dragging').addClass('dragging');
     });
     $("#form-ocm").on('dragend', '.draggable-container', function(e) { 
         var $this = $(e.target).closest('.draggable');
         $this.removeClass('dragging');
     });
     $("#form-ocm").on('dragover', '.draggable-container', function(e) { 
          e.preventDefault();
     });
     $("#form-ocm").on('dragenter', '.draggable-container', function(e) { 
         e.preventDefault();
         var $this = $(e.target).closest('.draggable');
         var _container = $(e.target).closest('.draggable-container')[0];
         if ($this[0] !== dragged && container === _container) {
             $this.removeClass('dropable').addClass('dropable');
         }
     });
     $("#form-ocm").on('dragleave', '.draggable-container', function(e) { 
          e.preventDefault();
          var _container = $(e.target).closest('.draggable-container')[0];
          var $this = $(e.target).closest('.draggable');
          if ($this[0] !== dragged && container === _container) {
            $this.removeClass("dropable");
          }
     });
     $("#form-ocm").on('drop', '.draggable-container', function(e) {
          var $this = $(e.target).closest('.draggable');
          var _container = $(e.target).closest('.draggable-container')[0];
          if ($this[0] !== dragged && container === _container) { 
             var from = $(dragged);
             var to = $(e.target).closest('.draggable');

             var from_dest = from.prev('.draggable')[0] || from[0];
             var to_dest = to.prev('.draggable')[0] || to[0];

             if (to_dest === from_dest) {
                if (from.prev()[0] === to[0]) from.after(to);
                else from.before(to);
             } else {
                $(to_dest).after(from);
                $(from_dest).after(to);
             }
          }
          $this.parent().find('.draggable').removeClass('dropable dragging');
     });
 }
/* End of common function */

/* Common Events across the modules */
$(document).ready(function (){
    // Check/Uncheck Al 
    $('#ocm-container').on('click', '.ocm-check-uncheck', function(e) {
        e.preventDefault();
        var checked = $(this).attr('rel') == 'checked';
        $(this).closest('.ocm-checkgroup').find('.ocm-checkgroup-checkbox input[type="checkbox"]').prop("checked",checked);
    });

    // Removel All 
    $('#ocm-container').on('click', '.ocm-remove-all', function(e) {
        e.preventDefault();
        $(this).parent().prev('.ocm-autofill-box').html('');
    });

     // More help 
    $('#ocm-container').on('click', '.ocm-more a[rel]', function(e) {
        e.preventDefault();
        var key = $(this).attr('rel');
        var help_container = $(this).parent().find('.ocm-more-container');
        if (more_help[key]) {
            var _more_html = help_container.html() ? '' : more_help[key];
            help_container.html(_more_html);
            if (_more_html) {
                help_container.show();
            } else {
                help_container.hide();
            }
        }
    });

    // Auto complete & item delete
    $('#ocm-container').on('click', '.ocm-autofill-item i', function() {
        $(this).parent().remove();
    });
    $('input.ocm-autofill').autocomplete(ocm.complete);
    
    // Quick Search
    $("#ocm-container").on('keyup','.ocm-search', function(e) {
        e.preventDefault();
        var list = $(this).closest('.ocm-checkgroup').find('.ocm-checkgroup-checkbox label');
        var keyword = $(this).val().toLowerCase();
        list.each(function() {
            if (keyword && $(this).text().toLowerCase().indexOf(keyword) == -1) {
                $(this).hide();
            } else {
               $(this).show();
            }
        });
    });
    
    $('#ocm-container').on('mouseover', 'div[ocm-attr]', function() {
        var attr_value = $(this).attr('ocm-attr');
        var att_nodes = $('div[ocm-attr="'+attr_value+'"]');
        if (att_nodes.length > 1) {
            att_nodes.addClass('ocm-hover-row');
        }
    });
    $('#ocm-container').on('mouseleave', 'div[ocm-attr]', function() {
        var attr_value = $(this).attr('ocm-attr');
        var att_nodes = $('div[ocm-attr="'+attr_value+'"]');
        if (att_nodes.length > 1) {
            att_nodes.removeClass('ocm-hover-row');
        }
    });
    
    /* Placeholder selection on click */
    $(document).on('click', 'input.ocm-placeholder', function() {
        this.select();
        document.execCommand('copy');
    });
});
/* End of common events */