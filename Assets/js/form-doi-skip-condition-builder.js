(function (Mautic, mQuery){
    Mautic.onFormDoiConditionsBuilder = function() {
        mQuery('#available-conditions-list').on('change', function() {
            const value = mQuery(this).val()
            if (value) {
                addLeadListFilter(mQuery(this).val(),mQuery('option:selected',this).data('field-object'));
                mQuery(this).val('');
                mQuery(this).trigger('chosen:updated');
            }
        });

        mQuery('#doi-skip-condition-list .skip-condition-panel').each( function (index, filter) {
            attachEvents(mQuery(filter));
        });

        initSortableForConditions();
        attachJsUiOnFilterForms();
        formFieldChangesListener();
    };

    const addLeadListFilter = function (elId, elObj) {
        var filterId = '#available-doi-skip-option_' + elObj + '_' + elId;
        var filterOption = mQuery(filterId);
        var label = filterOption.text();

        // Create a new filter

        var filterNum = getFilterCount();
        var prototypeStr = mQuery('[data-doi-skip-prototype]').data('doi-skip-prototype');
        var fieldType = filterOption.data('field-type');
        var fieldObject = filterOption.data('field-object');

        prototypeStr = prototypeStr.replace(/__name__/g, filterNum);
        prototypeStr = prototypeStr.replace(/__label__/g, label);

        // Convert to DOM
        const $prototype = mQuery(prototypeStr);

        var filterBase  = "mauticform[doiConfig][skipConditions][" + filterNum + "]";
        var filterIdBase = "mauticform_doiConfig_skipConditions_" + filterNum + "_";

        if (mQuery('#doi-skip-condition-list div.panel').length === 0) {
            // First filter so hide the glue footer
            $prototype.find(".panel-heading .panel-glue").addClass('hide');
        }

        if (fieldObject === 'company') {
            $prototype.find(".object-icon").removeClass('ri-user-6-fill').addClass('ri-building-2-line');
        } else if (fieldObject === 'form') {
            $prototype.find(".object-icon").removeClass('ri-user-6-fill').addClass('ri-survey-line');
        } else {
            $prototype.find(".object-icon").removeClass('ri-building-2-line').addClass('ri-user-6-fill');
        }
        $prototype.find(".inline-spacer").append(fieldObject);

        attachEvents($prototype);

        $prototype.find("input[name='" + filterBase + "[field]']").val(elId);
        $prototype.find("input[name='" + filterBase + "[type]']").val(fieldType);
        $prototype.find("input[name='" + filterBase + "[object]']").val(fieldObject);
        $prototype.appendTo('#doi-skip-condition-list');

        var operators = filterOption.data('field-operators');
        mQuery('#' + filterIdBase + 'operator').html('');
        mQuery.each(operators, function (label, value) {
            var newOption = mQuery('<option/>').val(value).text(label);
            newOption.appendTo(mQuery('#' + filterIdBase + 'operator'));
        });

        // Convert based on first option in list
        convertLeadFilterInput('#' + filterIdBase + 'operator');

        // Reposition if applicable
        Mautic.updateFilterPositioning(mQuery('#' + filterIdBase + 'glue'));
    };

    const convertLeadFilterInput = function(el) {
        var operatorSelect = mQuery(el);
        // Extract the filter number
        var regExp = /_skipConditions_(\d+)_operator/;
        var matches = regExp.exec(operatorSelect.attr('id'));
        var filterNum = matches[1];
        var fieldAlias = mQuery('#mauticform_doiConfig_skipConditions_'+filterNum+'_field');
        var fieldObject = mQuery('#mauticform_doiConfig_skipConditions_'+filterNum+'_object');
        var filterValue = mQuery('#mauticform_doiConfig_skipConditions_'+filterNum+'_properties_filter').val();
        var filterId  = '#mauticform_doiConfig_skipConditions_' + filterNum + '_properties_filter';
        const formId = mQuery('#mauticform_sessionId').val();

        loadFilterForm(formId, filterNum, fieldObject.val(), fieldAlias.val(), operatorSelect.val(), function(propertiesFields) {
            var selector = '#mauticform_doiConfig_skipConditions_'+filterNum;
            mQuery(selector+'_properties').html(propertiesFields);
            triggerOnPropertiesFormLoadedEvent(selector, filterValue);
        });

        Mautic.setProcessorForFilterValue(filterId, operatorSelect.val());
    }

    const triggerOnPropertiesFormLoadedEvent = function(selector, filterValue) {
        mQuery('#doi-skip-condition-list').trigger('filter.properties.form.loaded', [selector, filterValue]);
    };

    const initSortableForConditions = function() {
        var bodyOverflow = {};
        mQuery('#doi-skip-condition-list').sortable({
            items: '.panel',
            helper: function(e, ui) {
                ui.children().each(function() {
                    if (mQuery(this).is(":visible")) {
                        mQuery(this).width(mQuery(this).width());
                    }
                });

                // Fix body overflow that messes sortable up
                bodyOverflow.overflowX = mQuery('body').css('overflow-x');
                bodyOverflow.overflowY = mQuery('body').css('overflow-y');
                mQuery('body').css({
                    overflowX: 'visible',
                    overflowY: 'visible'
                });

                return ui;
            },
            scroll: true,
            axis: 'y',
            stop: function(e, ui) {
                // Restore original overflow
                mQuery('body').css(bodyOverflow);
                reorderSkipConditions();
            }
        });
    }

    const attachJsUiOnFilterForms = function() {
        mQuery('#doi-skip-condition-list').on('filter.properties.form.loaded', function(event, selector, filterValue) {
            Mautic.activateChosenSelect(selector + '_properties select');
            var fieldType = mQuery(selector + '_type').val();
            var fieldAlias = mQuery(selector + '_field').val();
            var filterFieldEl = mQuery(selector + '_properties_filter');

            if (filterValue) {
                filterFieldEl.val(filterValue);
                if (filterFieldEl.is('select')) {
                    filterFieldEl.trigger('chosen:updated');
                }
            }

            if (fieldType === 'lookup') {
                Mautic.activateLookupTypeahead(filterFieldEl.parent());
            } else if (fieldType === 'datetime') {
                filterFieldEl.datetimepicker({
                    format: 'Y-m-d H:i',
                    lazyInit: true,
                    validateOnBlur: false,
                    allowBlank: true,
                    scrollMonth: false,
                    scrollInput: false
                });
            } else if (fieldType === 'date') {
                filterFieldEl.datetimepicker({
                    timepicker: false,
                    format: 'Y-m-d',
                    lazyInit: true,
                    validateOnBlur: false,
                    allowBlank: true,
                    scrollMonth: false,
                    scrollInput: false,
                    closeOnDateSelect: true
                });
            } else if (fieldType === 'time') {
                filterFieldEl.datetimepicker({
                    datepicker: false,
                    format: 'H:i',
                    lazyInit: true,
                    validateOnBlur: false,
                    allowBlank: true,
                    scrollMonth: false,
                    scrollInput: false
                });
            } else if (fieldType === 'lookup_id') {
                var displayFieldEl = mQuery(selector + '_properties_display');
                var fieldCallback = displayFieldEl.attr('data-field-callback');
                if (fieldCallback && typeof Mautic[fieldCallback] === 'function') {
                    var fieldOptions = displayFieldEl.attr('data-field-list');
                    Mautic[fieldCallback](selector.replace('#', '') + '_properties_display', fieldAlias, fieldOptions);
                }
            }
        });

        // Trigger event so plugins could attach other JS magic to the form.
        mQuery('#doi-skip-condition-list .panel').each(function() {
            triggerOnPropertiesFormLoadedEvent('#' + mQuery(this).attr('id'));
        });
    };

    const loadFilterForm = function(formId, filterNum, fieldObject, fieldAlias, operator, resultHtml, search = null) {
        const url = mQuery('[data-doi-render-skip-condition-properties]').data('doi-render-skip-condition-properties');
        mQuery.ajax({
            showLoadingBar: true,
            url: url,
            type: 'GET',
            data: {
                fieldAlias: fieldAlias,
                fieldObject: fieldObject,
                operator: operator,
                filterNum: filterNum,
                search: search,
                formId: formId
            },
            success: function (response) {
                Mautic.stopPageLoadingBar();
                resultHtml(response.viewParameters.form);
            },
            error: function (request, textStatus, errorThrown) {
                Mautic.processAjaxError(request, textStatus, errorThrown);
            }
        });
    }

    const attachEvents = function($filter) {
        attachRemoveEvents($filter);
    };


    const attachRemoveEvents = function($filter) {
        $filter.find('a.remove-selected').each(function (index, el) {
            mQuery(el).on('click', function () {
                $filter.animate(
                    {'opacity': 0},
                    'fast',
                    function () {
                        // Remove the existing tooltip
                        mQuery('*[role="tooltip"]').tooltip('destroy');
                        mQuery(this).remove();
                        reorderSkipConditions();
                    }
                );
            });
        });
    };

    const getFilterCount = function() {
        return mQuery('#doi-skip-condition-list').children('.skip-condition-panel').length;
    };

    const reorderSkipConditions = function() {
        // Update the filter numbers sot that they are ordered correctly when processed and grouped server side
        let counter = 0;
        const $filters = mQuery('#doi-skip-condition-list .panel');
        const namePrefix  = "mauticform[doiConfig]";
        const idPrefix = "mauticform_doiConfig";

        $filters.each(function() {
            const $filter = mQuery(this);
            $filter.attr('id',idPrefix + '_skipConditions_'+counter);
            Mautic.updateFilterPositioning($filter.find('select.glue-select').first());
            $filter.find('[id^="' + idPrefix + '_skipConditions_"]').each(function() {
                const $element = mQuery(this);
                var id     = $element.attr('id');
                var name   = $element.attr('name');
                var suffix = id.split(/[_]+/).pop();

                var isProperties = id.includes("_properties_");

                if (idPrefix + '_skipConditions___name___filter' === id) {
                    return true;
                }

                if (name) {
                    if (isProperties) {
                        const suffixIdMatch = id.match(/_properties_(.*)$/);
                        const suffixNameMatch = name.match(/\[properties\](.*)$/);
                        const suffixId = suffixIdMatch ? suffixIdMatch[1] : suffix;
                        const suffixName = suffixNameMatch ? suffixNameMatch[1] : suffix;
                        var newName = namePrefix + '[skipConditions][' + counter + '][properties]' + suffixName;
                        suffix = 'properties_' + suffixId;
                    } else {
                        var newName = namePrefix + '[skipConditions][' + counter + '][' + suffix + ']';
                        if (name.slice(-2) === '[]') {
                            newName += '[]';
                        }
                    }
                    $element.attr('name', newName);
                }
                $element.attr('id', idPrefix + '_skipConditions_'+counter+'_'+suffix);

                // Destroy the chosen and recreate
                if ($element.is('select') && suffix === 'properties_filter') {
                    Mautic.destroyChosen($element);
                    Mautic.activateChosenSelect($element);
                }

                if (mQuery(this).is(':radio') && id.includes("_dateTypeMode_")) {
                    if (mQuery(this).closest('label').hasClass('active')) {
                        mQuery(this).click();
                    }
                }
            });

            $filter.find('.panel-heading').css('width', ''); // Something is setting width. Remove it.

            ++counter;
        });

        $filters.find('.panel-glue').removeClass('hide');
        $filters.first().find('.panel-glue').addClass('hide');

        const $tooltips = $filters.find("*[data-toggle='tooltip']");
        $tooltips.each(function() {
            mQuery(this).tooltip({html: true, container: 'body'});
        });
    };

    const formFieldChangesListener = function() {
        mQuery(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('forms/field/new')) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    // Check if this is a form field response
                    if (response.mauticContent === 'formField' && response.success === 1) {
                        const $select = mQuery('#available-conditions-list');
                        const infoMessage = $select.data('new-fields-info');
                        mQuery('option.new-field-notification', $select).remove();
                        const infoOption = mQuery('<option class="new-field-notification" disabled>' + infoMessage + '</option>');
                        mQuery('optgroup[label="form"]', $select).prepend(infoOption);
                        $select.trigger('chosen:updated');
                    }
                } catch (e) {
                    console.log('Error processing form field response:', e);
                }
            }
        });
    }


    Mautic.doiConvertLeadFilterInput = convertLeadFilterInput;
    Mautic.doiReorderSkipConditions = reorderSkipConditions;
}(Mautic, mQuery));