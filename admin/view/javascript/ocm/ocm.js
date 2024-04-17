//author opencartmart
//version 1.1.5
;(function(window){
    var _methods = [];
    var $ = $ || jQuery; // mijoshop fixes
    var divider = _ocm.divider || '/';
    var data_prefix = _ocm.oc4 ? 'data-bs' : 'data';
    var auto_config = {
        category: {
            url: 'index.php?route=catalog/category' + divider + 'autocomplete',
            id: 'category_id'
        },
        product: {
            url: 'index.php?route=catalog/product' + divider + 'autocomplete',
            id: 'product_id'
        },
        manufacturer_rule: {
            url: 'index.php?route=catalog/manufacturer' + divider + 'autocomplete',
            id: 'manufacturer_id'
        },
        filter_rule: {
            url: 'index.php?route=catalog/filter/autocomplete',
            id: 'filter_id'
        },
        option: {
            url: 'index.php?route=' + _ocm.path + divider + 'getOption',
            id: 'option_value_id'
        },
        attribute: {
            url: 'index.php?route=' + _ocm.path + divider + 'getAttribute',
            id: 'attribute_id'
        },
        location_rule: {
            url: 'index.php?route=' + _ocm.path + divider + 'getLocation',
            id: 'name'
        },
        customer_all: {
            url: 'index.php?route=' + _ocm.path + divider + 'getCustomer',
            id: 'customer_id'
        },
        /* OCM Browser  */
        manufacturer_id: {
             url: 'index.php?route=catalog/manufacturer' + divider + 'autocomplete',
             id: 'manufacturer_id'
        },
        category_id: {
            url: 'index.php?route=catalog/category' + divider + 'autocomplete',
            id: 'category_id'
        },
        filter_id: {
             url: 'index.php?route=catalog/filter/autocomplete',
             id: 'filter_id'
        },
        manufacturer: {
             url: 'index.php?route=catalog/manufacturer' + divider + 'autocomplete',
             id: 'manufacturer_id'
        },
        /* Other modules */
        category_rule: {
            url: 'index.php?route=catalog/category' + divider + 'autocomplete',
            id: 'category_id'
        },
        product_rule: {
            url: 'index.php?route=catalog/product' + divider + 'autocomplete',
            id: 'product_id'
        }
    };
    var ocm_complete = {
        debounce: null,
        source: function source(request, response) {
            var _this = $(this);
            var attr = _this.attr('attr');
            if (!attr) {
                _this = $(window._ocm_autofill_node);
                attr = _this.attr('attr');
            }
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
            var _this = $(this);
            var attr = _this.attr('attr');
            if (!attr) {
                _this = $(window._ocm_autofill_node);
                attr = _this.attr('attr');
            }
            var config = ocm_complete.getConfig(attr);
            if (config && attr == config.id) {
                var container = _this.closest('div[ocm-attr]');
                _this.val(item['label']);
                container.find('input.' + config.id).val(item['value']);
            } else if (config && config.handler) {
                config.handler(item, _this);
            } else {
                var container = _this.closest('div[ocm-attr]').find('.ocm-autofill-box');
                var name = container.attr('name');
                _this.val('');
                if (container.length && name) {
                    $('.ocm-autofill-item[value="' + item['value'] + '"]', container).remove();
                    container.append('<div value="' + item['value'] + '" class="ocm-autofill-item"><i class="fa fas fa-minus-circle"></i> ' + item['label'] + '<input type="hidden" name="' + name + '" value="' + item['value'] + '" /></div>');
                } else if (config.id) {
                    $(config.id).val(item[config.key || 'value']);
                }
            }
            //OC has bug, sometimes it fails to close, so let close it
            if (_ocm && !_ocm.oc4 && _this.nextAll('.dropdown-menu').length) {
                _this.nextAll('.dropdown-menu').hide();
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
            if (window.onGrabMethodData) { // event for exnteral place
                window.onGrabMethodData.call(null);
            }
            _methods = [];
            $('#ocm-method-list > li > a').each(function(i) {
                var tab_id = $(this).attr('rel');
                if (!tab_id) return;
                var data = $('#ocm-method-' + tab_id + ' :input').serialize();
                if (data) {
                    data += '&tab_id=' + tab_id + '&sort_order=' + i;
                    _methods.push(data);
                }
            });
            return this;
        },
        saveGeneral: function() {
            if (window.onGrabGeneralData) { // event for exnteral place
                window.onGrabGeneralData.call(null);
            }
            var data = $('#tab-global :input').serialize();
            //sorting order fix without loading it
            var sorted = [];
            $('#ocm-method-list > li > a').each(function(i) {
                var tab_id = $(this).attr('rel');
                if (!tab_id) return;
                sorted.push({
                    tab_id: tab_id,
                    sort_order: i
                });
            });
            if (sorted.length) {
                data += '&' + $.param({sorted: sorted});
            }
            ocm_action.doAjax('save_general', data);
        },
        saveMethod: function() {
            var data = _methods.pop();
            if(data) {
                ocm_action.doAjax('quick_save', data);
            }
        },
        saveMethodById: function(tab_id) {
            if (!tab_id) return;
            if (window.onGrabMethodData) { // event for exnteral place
                window.onGrabMethodData.call(null);
            }
            var sort_order = 1;
            $('#ocm-method-list > li > a').each(function(i) {
                var _tab_id = $(this).attr('rel');
                if (!_tab_id) return;
                if (parseInt(_tab_id) === parseInt(tab_id)) {
                    sort_order = i;
                } 
            });
            var data = $('#ocm-method-' + tab_id + ' :input').serialize() + '&tab_id=' + tab_id + '&sort_order=' + sort_order;
            ocm_action.doAjax('quick_save', data);
        },
        loadMethod: function(tab_id, fn) {
            $('#ocm-method-' + tab_id).html('<i class="fa fas fa-spinner fa-spin ocm-loader"></i>');
            var data = {
                tab_id: tab_id
            };
            this.loaderStatus = true; // don't show default loader
            ocm_action.doAjax('load_method', data, function(json) {
                if (json && json.html && json.tab_id) {
                    $('#ocm-method-' + tab_id).replaceWith(json.html);
                    if (fn) {
                        fn.call(null, tab_id);
                    }
                }
            });
        },
        clearCache: function() {
            ocm_action.doAjax('clearCache');
        },
        deleteMethod: function(tab_id) {
             var data = {
                tab_id: tab_id
            };
            ocm_action.doAjax('delete', data);
        },
        doAjax: function(action, data, fn, noLoader) {
            if (!noLoader) {
                this.showLoader();
            }
            var divider = _ocm.divider || '/';
            var url = /\//.test(action) ? action : ocm_action.url + divider + action;
            var type = data ? 'POST' : 'GET';
            var _req = {
                url: url + (_ocm.token && url.indexOf('token') == -1 ? '&' + _ocm.token : ''),
                dataType: 'json',
                type: type,
                success: function(json) {
                    if (json['error']) {
                        ocm_action.hideLoader();
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
            loader = '<div class="ocm-modal-loader"><i class="fa fas fa-spinner fa-spin"></i></div>';
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
                $(btn).prepend('<i class="fa fas fa-spinner fa-spin"></i>');
            }
        };
        this.hideButtonLoader = function hideButtonLoader() {
            $('#' + this.id).find('.modal-footer button i').remove();
        };
        this.close = function close() {
            $('#' + this.id).modal('hide');
            $('div.global-waiting').remove();
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
                attr += 'data-backdrop="static" data-keyboard="false" data-backdrop="static" data-keyboard="false"';
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
                    var closeIcon = _ocm.oc4 ? '&times;' : '';
                    popup += '  <button type="button" class="close btn-close ocm-modal-close">' + closeIcon + '</button>';
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
            manufacturer: [
                {
                    title: 'ID',
                    value: 'manufacturer_id',
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
            ],
            filter: [
                {
                    title: 'ID',
                    value: 'filter_id',
                },
                {
                    title: 'Name',
                    value: 'name',
                }
            ],
            option_value: [
                {
                    title: 'ID',
                    value: 'option_value_id',
                },
                {
                    title: 'Name',
                    value: 'name',
                }
            ]
        };

        var keyword_filter = '<div class="ocm-browser-filter">';
        keyword_filter += '   <input type="text" name="filter[keyword]" value="" placeholder="Enter keyword" class="form-control ocm-browser-keyword" />';
        keyword_filter += '<button class="btn btn-primary ocm-browser-btn" name="ocm-browser-btn" type="button"><i class="fa fas fa-circle-o-notch fa-spin loading"></i>&nbsp;Search</button>'
        keyword_filter += '  </div>';

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

        var filters = {
            category: keyword_filter,
            manufacturer: keyword_filter,
            product: product_fitler,
            option_value: keyword_filter,
            filter: keyword_filter
        };
        var titles = {
            category: 'Category',
            manufacturer: 'Manufacturer',
            product: 'Product',
            option_value: 'Option',
            fiter: 'Filter',
        };
        var divider = _ocm.divider || '/';
        var requests = {
            category: {
                url: 'index.php?route=catalog/category' + divider + 'autocomplete',
                get: function() {
                    return '&filter_name=' + $('.ocm-browser-keyword').val();
                }
            },
            manufacturer: {
                url: 'index.php?route=catalog/manufacturer' + divider + 'autocomplete',
                get: function() {
                    return '&filter_name=' + $('.ocm-browser-keyword').val();
                }
            },
            filter: {
                url: 'index.php?route=catalog/filter/autocomplete',
                get: function() {
                    return '&filter_name=' + $('.ocm-browser-keyword').val();
                }
            },
            product: {
                url: 'getProducts',
                post: function() {
                    return $('.ocm-browser-filter [name^="filter"]:input');
                },
                clean: function() {
                    if ($('.ocm-browser-filter [attr="manufacturer_id"]').val() == '') {
                        $('.ocm-browser-filter input.manufacturer_id').val('');
                    }
                    if ($('.ocm-browser-filter [attr="category_id"]').val() == '') {
                        $('.ocm-browser-filter input.category_id').val('');
                    }
                }
            },
            option_value: {
                url: 'getOption',
                get: function() {
                    return '&filter_name=' + $('.ocm-browser-keyword').val();
                }
            }
        };
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
        // extrernal api to make cusotm browser 
        this.addBrowser = function(type, setting) {
            if (setting.filter) {
                filters[type] = setting.filter;
            }
            if (setting.field) {
                fields[type] = setting.field;
            }
            if (setting.request) {
                requests[type] = setting.request;
            }
            if (setting.title) {
                titles[type] = setting.title;
            }
        };
        this.requestData = function requestData() {
            var data;
            var action = requests[this.type];
            var url = action.url;
            if (typeof action.clean === 'function') {
                action.clean.call(null);
            }
            if (typeof action.get === 'function') {
                url += action.get.call(null);
            }
            if (typeof action.post === 'function') {
                data = action.post.call(null);
            }
            // if the same page contains multile _ocm, let update it by actual module path
            if (this.params.path) {
                url = 'index.php?route=' + this.params.path + '/' + url;
            }
            ocm_action.doAjax(url, data, onDataLoaded);
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
            this.id = this.type + '_id'; // id should be type + '_id'
            var _col_html = '';
            for (var i = 0; i < this.cols.length; i++) {
                var col = this.cols[i];
                _col_html += ' <td class="text-center">' + col.title + '</td>';
            }
            var modal_html = browser.replace('__COLS__', _col_html);
            modal_html = modal_html.replace('__FILTER__', filters[this.type]);

            this.modal.updateParams({title : (titles[this.type] || '')  + ' Browser'});
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
    function OCMTable(selector, context) {
        var self = this;
        this.message = 'There is nothing to show, please click `Add New` button to add!';
        this.attr = 'rel';
        this.selector = selector;
        this.context = context || window.document;
        this.setContext = function setContext(context) {
            this.context = context;
        };
        this.setSelector = function setSelector(selector) {
            this.selector = selector;
        };
        this.setMessage = function setMessage(message) {
            this.message = message;
        };
        this.setAttr = function setMessage(attr) {
            this.attr = attr;
        };
        this.next = function next() {
            var next = 0;
            $(this.selector, this.context).find('tr[' + self.attr + ']').each(function(){
                 if (parseInt($(this).attr(self.attr)) > next) {
                    next = parseInt($(this).attr(self.attr));
                 }
            });
            next++;
            return next;
        };
        this.toggle = function toggle() {
            if ($(this.selector, this.context).find('tr[' + self.attr + ']').length == 0) {
                if ($(this.selector, this.context).find('tr.no-row').length == 0) {
                    $(this.selector, this.context).append('<tr class="no-row"><td colspan="10">' + self.message + '</td></tr>');
                }
            } else {
                $(this.selector, this.context).find('tr.no-row').remove();
            }
        };
        this.add = function add(row) {
            $(this.selector, this.context).append(row);
            this.toggle();
        }
        this.remove = function remove(node) {
            $(node).closest('tr').remove();
            this.toggle();
        }
        this.removeAll = function removeAll(node) {
            $(this.selector, this.context).find('tr').remove();
            this.toggle();
        }
        this.init = function init() {
            $(document).on('click','.ocm-row-remove',function() {
                self.remove(this);
            });
            $(document).on('click','.ocm-row-remove-all',function(){
                self.removeAll();
            });
        }
        this.init();
    }
    window.ocm = {
        complete: ocm_complete,
        action: ocm_action,
        util: ocm_util,
        browser: new OCMBrowser(),
        modal: new OCMModal(),
        table: new OCMTable('.ocm-range-container tbody'),
        model: {
            table: OCMTable,
            modal: OCMModal
        }
    };
})(window);

/* Common function */
var _ocm_name;
var _ocm_autofill_node = null; // For OC 2.0.0.0 :(
var $ = $ || jQuery; // mijoshop fixes
var data_prefix = _ocm.oc4 ? 'data-bs' : 'data';
/* Datepicker setting */
var _dp_option = {
      format: 'YYYY-MM-DD',
      locale: 'en-gb',
      allowInputToggle: true
};

// v4.0
// Daterangepicker
if (_ocm.oc4) {
    // v4 uses daterangepicker so make a datetimepicker wrapper to make compatible
    $.fn.datetimepicker  = function (options) {
        $('.date').daterangepicker({
            singleDatePicker: true,
            autoApply: true,
            locale: {
                format: options.format,
            }
        });

        $('.time').daterangepicker({
            singleDatePicker: true,
            datePicker: false,
            autoApply: true,
            timePicker: true,
            timePicker24Hour: true,
            locale: {
                format: 'HH:mm'
            }
        }).on('show.daterangepicker', function (ev, picker) {
            picker.container.find('.calendar-table').hide();
        });

        $('.datetime').daterangepicker({
            singleDatePicker: true,
            autoApply: true,
            timePicker: true,
            timePicker24Hour: true,
            locale: {
                format: options.format + ' HH:mm'
            }
        });
    };
}

// Editor
function initEditor(selector, options) {
    var config;
    if (typeof CKEDITOR != 'undefined') {
        config = {
            language: 'en'
        };
        options = Object.assign({}, config, options);
        $(selector).ckeditor(options);
    } else {
        config = {
            height: 100
        };
        options = Object.assign({}, config, options)
        $(selector).summernote(options);
    }
}    

function onItemsSelection(type, selected, result) {
    if (type == 'category') {
        ocm.action.doAjax('fetchCategoy', selected, onBatchSelction);
    } else {
        var json = [];
        selected.each(function(i) {
            var _id = parseInt($(this).val());
            var key = type + '_id';
            for (var i = 0; i < result.length; i++) {
                if (parseInt(result[i][key]) == _id) {
                    var item = {};
                    item.name = result[i].name,
                    item[key] = result[i][key]
                    json.push(item);
                }
            }
        });
        onBatchSelction(json, type);
    }
}
/* Apply selected list to the dom */
function onBatchSelction(json, type) {
    type = type || 'category';
    var context = typeof ocm_tab != 'undefined' ? $('#ocm-method-' + ocm_tab) : document;
    var container = $('div[name="'+_ocm_name+'"]', context); 
    var name = container.attr('name');
    for (var i = 0; i < json.length; i++) {
        var item = json[i];
        $('.ocm-autofill-item[value="' + item[type + '_id'] + '"]', container).remove();
        container.append('<div value="' + item[type +'_id'] + '" class="ocm-autofill-item"><i class="fa fas fa-minus-circle"></i> ' + item['name'] + '<input type="hidden" name="' + name + '" value="' + item[type + '_id'] + '" /></div>');
    }
}
function getNextTab() {
    var next_tab = $('#ocm-container').find('div.ocm-method').length;
    next_tab = parseInt(next_tab) + 1;
    while ($('#ocm-method-' + next_tab).length != 0) {
        next_tab++;
    }
    return next_tab;
}
/* Add new method to the left nav i.e method list */
function addToMethodList(id, name) {
    if (!name) {
        name = 'Untitled Item ' + id;
    }
    var tab = '<li draggable="true" class="nav-item draggable">'
                 + '<a draggable="false" class="nav-link" ' + data_prefix + '-toggle="tab" href="#ocm-method-' + id + '" rel="' + id + '">' + name + '</a>'
                 + '<i class="fa fas fa-arrows-alt"></i>'
               + '</li>';
    $('#ocm-method-list').append(tab);
}

/* Get method list for auto completion */
function getMethodList(request) {
    var methods = [];
    $('#ocm-method-list > li > a').each(function(i) {
        var tab_id = $(this).attr('rel');
        var name = $(this).text();
        if (!tab_id) return;
        if (ocm_tab != tab_id && name.toLowerCase().indexOf(request) != -1) {
            methods.push({
                label: name,
                value: tab_id
            });
        }
    });
    // additional data if available  
    if (window.additional_methods) {
        $.each(additional_methods, function(key, each) {
            if (each.name.toLowerCase().indexOf(request) != -1) {
                methods.push({
                    label: each.name,
                    value: each.value
                });
            }
        });
    }
    if (methods.length > 10) {
        methods = methods.slice(0, 10);
    }
    return methods;
}
function copyMethod(tabId, input_domain) {
    var no_of_tab = getNextTab();
    var tab_item = $('#ocm-method-'+tabId).clone();
    var tab_html = '<div id="ocm-method-'+no_of_tab+'" class="tab-pane ocm-method">'+tab_item.html()+'</div>';
    tab_html = tab_html.replace(new RegExp(input_domain + '\\[([a-z_]+)\\]', 'igm'), input_domain + '[$1]');
    tab_html = tab_html.replace(/_(\d+)/g, '_'+no_of_tab); 
    $('#ocm-container').append(tab_html);
    addToMethodList(no_of_tab, $('#ocm-method-list a[rel="' + tabId +'"]').html());
      
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
/* Live Debgging */
var debugInternal;
function debugBrowser() {
    function closeDebug() {
      clearInterval(debugInternal);
      $('#ocm-debug').remove();  
    }
    var debugUI = '<div id="ocm-debug">'
                   + '<div id="ocm-debug-header">'
                   + 'Live Debugging Log' 
                   + '<div id="ocm-debug-close">×</div>'
                   + '</div>'
                   + '<div id="ocm-debug-content"><div id="ocm-debug-loader">Loading...</div></div>'
                   + '</div>';

    closeDebug();
    $('body').append(debugUI);
    $('#ocm-debug-close').click(closeDebug);
    debugInternal = setInterval(function() {
        ocm.action.doAjax('fetchDebug', [], function(json){
            $('#ocm-debug-loader').remove();
            var current_log = $('#ocm-debug-content').html();
            current_log = current_log.replace(/<div\s+class="text-(success|danger)">.*?<\/div>/gi, '');
            var log = json.log;
            if (!log) {
                log = '<div class="text-success">Only shows recent log. Try to checkout. Waiting for logs...</div>';
            } else {
                current_log = '';
                log = '<span> Print Time: ' +  new Date().toLocaleTimeString('en-US', { hour12: true}) + '</span><br>' + log;
            }
            if (log) {
                $('#ocm-debug-content').html(log + current_log);
            }
        }, true);
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
     // disable drag event on inputs
     $("#form-ocm").on('focus', '.draggable-container input', function(e) {
         $(this).closest('.draggable').attr('draggable', false);
     });
     $("#form-ocm").on('focusout', '.draggable-container input', function(e) {
         $(this).closest('.draggable').attr('draggable', true);
     });
}
function enableEvents(no_of_tab){
    var ocm_method = $('#ocm-method-' + no_of_tab);

    if (_ocm.oc4) {
        var tabElm = $('#ocm-method-list a[rel="' + no_of_tab +'"]');
        var tab = new bootstrap.Tab(tabElm);
        tab.show();
    } else {
        $('#ocm-method-list a[rel="' + no_of_tab +'"]').trigger('click');
    }
    
    $("[" + data_prefix + "-toggle='tooltip']", ocm_method).tooltip();
    $('input.ocm-autofill', ocm_method).autocomplete(ocm.complete);
    $('.date').datetimepicker(_dp_option);
}

/* Common Events across the modules */
$(document).ready(function () {
    $('#ocm-method-list').css('max-height', window.innerHeight);
    /* OCM Browser  for batch selection */
    $('#ocm-container').on('click', '.ocm-browser', function(e) {
        e.preventDefault();
        _ocm_name = $(this).attr('name');
        ocm.browser.show({
            type: $(this).attr('rel'),
            fn: onItemsSelection
        });
    });
    // dependent fields
    $("#ocm-container").on('change', '[ocm-on]', function() {
        var context = typeof ocm_tab != 'undefined' ? $('#ocm-method-' + ocm_tab) : document;
        var nodeName = this.nodeName.toLowerCase();
        var dependant = $('div[ocm-attr="' + $(this).attr('ocm-on') + '"]', context);
        if (!dependant.length && isNaN($(this).val())) {
            dependant = $('div.' + $(this).val(), context);
            var hasDependantNode = $('div.' + $(this).attr('ocm-on'), context);
            if (hasDependantNode.length) {
                hasDependantNode.removeClass('ocm-visible');
            } else {
                dependant.length = 0;
            }
        }
        if (dependant.length) {
            dependant.removeClass('ocm-visible');
            var is_checkbox = (nodeName == 'input' && $(this).attr('type') !== 'radio');
            var visible = is_checkbox ? $(this).prop('checked') : $(this).val();
            if (is_checkbox) {
                if ($(this).attr('name').indexOf('_all') !== -1) {
                    visible = !visible;
                }
            }
            else if (isNaN(visible) === false) {
                visible = parseInt(visible);
            }
            if (visible) {
                dependant.addClass('ocm-visible');
            }
        }
    });

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

    // show selected
    $("#ocm-container").on('click','.ocm-check-selected', function(e) {
        e.preventDefault();
        var _off = !!$(this).data('_off');
        var list = $(this).closest('.ocm-checkgroup').find('.ocm-checkgroup-checkbox label');
        list.each(function() {
             if ($(this).find('input').prop('checked') || _off) {
                 $(this).show();
             } else {
                 $(this).hide();
             }
        });
        $(this).removeClass('selected-on');
        if (!_off) {
            $(this).addClass('selected-on');
        }
        $(this).data('_off', !_off);
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

    /* Saving action */
    $('#btn_ocm_save, #btn_ocm_quick').click(function() {
        var is_redirect = ($(this).attr('id') === 'btn_ocm_save');
        ocm.action.grabData().onEnd = function () {
            if (is_redirect) {
                location = _ocm.dashboard.replace(/&amp;/g, '&');
            }
        };
        ocm.action.saveGeneral();
    });
    $('#btn_ocm_cache').click(function() {
        ocm.action.clearCache();
    });
    $("#ocm-method-list").on("click","li",function(){
        ocm_tab = $(this).find('a').attr('rel');
        ocm.table.setContext('#ocm-method-' + ocm_tab);
        if (ocm_tab) {
            var ocm_method = $('#ocm-method-' + ocm_tab);
            if (!ocm_method.children().length) {
                ocm.action.loadMethod(parseInt(ocm_tab), function(tab_id) {
                    enableEvents(tab_id);
                    if (window.onOcmTabCreated) { // event for exnteral place
                        window.onOcmTabCreated.call(null);
                    }
                    if (tab_id == ocm_tab) {
                        $('#ocm-method-' + ocm_tab).removeClass('active').addClass('active');
                    }
                });
            }
            if (window.onOcmTabSwitch) { // event for exnteral place
                window.onOcmTabSwitch.call(null);
            }
        }
        if (window.scrollY > $('#ocm-container').offset().top) {
            $('html, body').animate({
                scrollTop: $('#ocm-container').offset().top
            }, 500);
        }
        if (more_help) {
            $.each(more_help, function(key, value) {
                more_help[key] = value.replace(/<id>[\w]+<\/id>/g, '<id>'+ocm_tab+'</id>');
            });
        }
    });
    /* Make first tab active  */
    $('#ocm-method-list a:first').trigger('click');

    /* Creating New method*/
    $('.add-ocm-new').on('click',function(e) {
        e.preventDefault();
        $this = $(this);
        var no_of_tab = getNextTab();
        var tab_html = tpl.method;
        tab_html = tab_html.replace('__ID__','ocm-method-'+no_of_tab);
        tab_html = tab_html.replace(/__INDEX__/g, no_of_tab);
        $('#ocm-container').append(tab_html);
        addToMethodList(no_of_tab);
        enableEvents(no_of_tab); 
        ocm_tab = no_of_tab;
        ocm.table.setContext('#ocm-method-' + ocm_tab);
        if (window.onOcmTabCreated) { // event for exnteral place
            window.onOcmTabCreated.call(null);
        }
    });
    /* End of creating new*/

    /* datepicker */
    $('.date').datetimepicker(_dp_option);
    if (_ocm.dnd) {
        enableDragDrop();
    }
    
    /*  Delete, copy and method display on type */
    $("#ocm-container").on('click', 'button.btn-ocm-delete', function() { 
        if(confirm('Are you sure to delete this method?')){
            ocm.action.deleteMethod(ocm_tab);
            $('#ocm-method-list a[rel="' + ocm_tab +'"]').remove();
            $('#ocm-method-' + ocm_tab).remove();
            $('#ocm-method-list a:first').tab('show');
        }
    });
    $("#ocm-container").on('click', 'button.btn-ocm-copy', function() { 
        if(confirm('Are you sure to copy this method?')){
            ocm_tab = copyMethod(ocm_tab, _ocm.name);
            enableEvents(ocm_tab);
            if (window.onOcmCopy) {
                window.onOcmCopy.call(null);
            }
        }
    });
    $("#ocm-container").on('click', 'button.btn-ocm-save', function() {
        var tab_id = $(this).closest('.ocm-method').attr('id').replace('ocm-method-', '');
        ocm.action.saveMethodById(parseInt(tab_id));
    });
    $("#ocm-container").on('keyup','input.display', function() {
        var method_name = $(this).val();
        if (method_name =='') method_name = 'Untitled Item ' + ocm_tab;
        $('#ocm-method-list a[rel="' + ocm_tab +'"]').html(method_name);
    });
    /* Placeholder selection on click */
    $(document).on('click', '.ocm-placeholder', function() {
        this.select();
        document.execCommand('copy');
    });
    // for OC 2.0.0.0, it does not provide dom properly
    $(document).on('click', 'input.ocm-autofill', function() {
        _ocm_autofill_node = this; 
    });
});
/* End of common events */