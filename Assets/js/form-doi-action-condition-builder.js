(function (Mautic, mQuery){
    Mautic.onFormDoiActionConditionsBuilder = function() {
        initializeBuilders();
        formFieldChangesListener();
        formNewActionListener();
        formDeleteActionListener();
    };

    const initializeBuilders = function() {
        const $actions = mQuery('div[data-doi-action]');
        const $templateSelect = mQuery('select[data-doi-action-available-conditions-list]');

        $actions.each(function() {
            const $action = mQuery(this);
            const actionId = $action.data('doi-action');
            initializeSingleBuilder(actionId, $action, $templateSelect);
        });
    };

    const initializeSingleBuilder = function(actionId, $action, $templateSelect) {
        // Skip if already initialized
        if ($action.data('doi-builder-initialized')) {
            return;
        }
        $action.data('doi-builder-initialized', true);

        // Clone and setup the select dropdown
        const $clonedSelect = $templateSelect.clone();
        $clonedSelect.attr('data-doi-action-id', actionId);
        $action.find('div[data-doi-action-available-conditions-list-container]').append($clonedSelect);

        // Move the form wrapper into the action container
        const $formWrapper = mQuery('#mauticform_doiActionConditionsConfig_actionConditions_' + actionId);
        if ($formWrapper.length) {
            $action.find('div[data-doi-action-condition-list]').html($formWrapper);
        } else {
            // When it's new action add a prototype
            const $prototypeWrapper = createActionConditionsPrototype(actionId);
            $action.find('div[data-doi-action-condition-list]').html($prototypeWrapper);
        }

        setupAddConditionsButton(actionId, $action);
        updateConditionsVisibility(actionId, $action);

        // Attach events to existing conditions
        const $container = getConditionsContainer(actionId);
        $container.children('.doi-condition-panel').each(function (index, condition) {
            attachRemoveEvents(actionId, mQuery(condition));
        });

        // Handle select change to add new conditions
        $clonedSelect.on('change', function() {
            const value = mQuery(this).val();
            if (value) {
                const $option = mQuery('option:selected', this);
                addCondition($option, $clonedSelect);
                mQuery(this).val('');
                mQuery(this).trigger('chosen:updated');
            }
        });

        // Attach UI handlers for filter forms
        attachJsUiOnFilterForms(actionId);

        // Initialize drag-and-drop sorting
        initSortableForConditions(actionId);
    };

    const setupAddConditionsButton = function(actionId, $action) {
        const $button = $action.find('[data-add-doi-conditions-button="' + actionId + '"]');

        $button.on('click', function(e) {
            e.preventDefault();
            showConditionsBuilder(actionId, $action);
        });
    };

    const showConditionsBuilder = function(actionId, $action) {
        const $buttonWrapper = $action.find('[data-add-doi-conditions-button-wrapper="' + actionId + '"]');
        const $builderWrapper = $action.find('[data-doi-action-condition-builder-wrapper="' + actionId + '"]');
        $buttonWrapper.hide();
        $builderWrapper.show();
    };

    const hideConditionsBuilder = function(actionId, $action) {
        const $buttonWrapper = $action.find('[data-add-doi-conditions-button-wrapper="' + actionId + '"]');
        const $builderWrapper = $action.find('[data-doi-action-condition-builder-wrapper="' + actionId + '"]');
        $builderWrapper.hide();
        $buttonWrapper.show();
    };

    const updateConditionsVisibility = function(actionId, $action) {
        const conditionCount = getConditionCount(actionId);
        if (conditionCount > 0) {
            showConditionsBuilder(actionId, $action);
        } else {
            hideConditionsBuilder(actionId, $action);
        }
    };

    const addCondition = function($option, $select) {
        const label = $option.text();
        const field = $option.val();                      // firstname|email etc.
        const fieldType = $option.data('field-type');     // text|number|datetime etc.
        const fieldObject = $option.data('field-object'); // lead|company|form
        const fieldOperators = $option.data('field-operators');
        const actionId = $select.data('doi-action-id');
        const $conditionContainer = getConditionsContainer(actionId);
        const filterNum = getConditionCount(actionId);
        const filterIdBase = "mauticform_doiActionConditionsConfig_actionConditions_" + actionId + "_conditions_" + filterNum + "_";
        const filterBase  = "mauticform[doiActionConditionsConfig][actionConditions][" + actionId + "][conditions][" + filterNum + "]";

        let prototypeStr = $select.data('doi-condition-prototype');
        prototypeStr = prototypeStr.replace(/__actionId__/g, actionId);
        prototypeStr = prototypeStr.replace(/__conditionIndex__/g, filterNum);
        prototypeStr = prototypeStr.replace(/__label__/g, label);

        // Convert to DOM
        const $prototype = mQuery(prototypeStr);

        if (filterNum === 0) {
            // First filter, so hide the glue footer
            $prototype.find(".panel-heading .panel-glue").addClass('hide');
        }

        if (fieldObject === 'company') {
            $prototype.find(".object-icon").removeClass('ri-user-6-fill').addClass('ri-building-2-line');
        } else {
            $prototype.find(".object-icon").removeClass('ri-building-2-line').addClass('ri-user-6-fill');
        }
        $prototype.find(".inline-spacer").append(fieldObject);

        attachRemoveEvents(actionId, $prototype);

        $prototype.find("input[name='" + filterBase + "[field]']").val(field);
        $prototype.find("input[name='" + filterBase + "[type]']").val(fieldType);
        $prototype.find("input[name='" + filterBase + "[object]']").val(fieldObject);
        $prototype.appendTo($conditionContainer);

        mQuery('#' + filterIdBase + 'operator').html('');
        mQuery.each(fieldOperators, function (label, value) {
            const newOption = mQuery('<option/>').val(value).text(label);
            newOption.appendTo(mQuery('#' + filterIdBase + 'operator'));
        });

        // Convert based on the first option in a list
        convertLeadFilterInput('#' + filterIdBase + 'operator');

        // Reposition if applicable
        updateConditionPositioning(mQuery('#' + filterIdBase + 'glue'));
    };

    const convertLeadFilterInput = function(el) {
        const operatorSelect = mQuery(el);
        const regExp = /_actionConditions_([^_]+)_conditions_(\d+)_operator/;
        const matches = regExp.exec(operatorSelect.attr('id'));

        if (!matches) {
            console.error('Could not parse operator select ID:', operatorSelect.attr('id'));
            return;
        }

        const actionId = matches[1];
        const conditionNum = matches[2];
        const fieldBase = 'mauticform_doiActionConditionsConfig_actionConditions_' + actionId + '_conditions_' + conditionNum;

        const fieldAlias = mQuery('#' + fieldBase + '_field');
        const fieldObject = mQuery('#' + fieldBase + '_object');
        const filterValue = mQuery('#' + fieldBase + '_properties_filter').val();
        const filterId = '#' + fieldBase + '_properties_filter';
        const formId = mQuery('#mauticform_sessionId').val();

        loadConditionForm(formId, actionId, conditionNum, fieldObject.val(), fieldAlias.val(), operatorSelect.val(), function(propertiesFields) {
            const selector = '#' + fieldBase;
            mQuery(selector + '_properties').html(propertiesFields);
            triggerOnPropertiesFormLoadedEvent(actionId, selector, filterValue);
        });

        Mautic.setProcessorForFilterValue(filterId, operatorSelect.val());
    };

    const getConditionsContainer = function(actionId) {
        return mQuery('#mauticform_doiActionConditionsConfig_actionConditions_' + actionId + '_conditions');
    };

    const triggerOnPropertiesFormLoadedEvent = function(actionId, selector, filterValue) {
        const $container = getConditionsContainer(actionId);
        $container.trigger('filter.properties.form.loaded', [selector, filterValue]);
    };

    const attachJsUiOnFilterForms = function(actionId) {
        const $container = getConditionsContainer(actionId);

        $container
            .off('filter.properties.form.loaded')
            .on('filter.properties.form.loaded', function(event, selector, filterValue) {
                Mautic.activateChosenSelect(selector + '_properties select');
                const fieldType = mQuery(selector + '_type').val();
                const fieldAlias = mQuery(selector + '_field').val();
                const filterFieldEl = mQuery(selector + '_properties_filter');

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
                    const displayFieldEl = mQuery(selector + '_properties_display');
                    const fieldCallback = displayFieldEl.attr('data-field-callback');
                    if (fieldCallback && typeof Mautic[fieldCallback] === 'function') {
                        const fieldOptions = displayFieldEl.attr('data-field-list');
                        Mautic[fieldCallback](selector.replace('#', '') + '_properties_display', fieldAlias, fieldOptions);
                    }
                }
            });

        // Trigger event for existing conditions
        mQuery('.doi-condition-panel', $container).each(function() {
            const selector = '#' + mQuery(this).attr('id');
            triggerOnPropertiesFormLoadedEvent(actionId, selector);
        });
    };

    const loadConditionForm = function(formId, actionId, conditionNum, fieldObject, fieldAlias, operator, resultHtml, search = null) {
        const url = mQuery('[data-doi-render-action-condition-properties]').data('doi-render-action-condition-properties');
        mQuery.ajax({
            showLoadingBar: true,
            url: url,
            type: 'GET',
            data: {
                fieldAlias: fieldAlias,
                fieldObject: fieldObject,
                operator: operator,
                actionId: actionId,
                conditionNum: conditionNum,
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
    };

    const attachRemoveEvents = function(actionId, $condition) {
        $condition.find('a.remove-selected').each(function (index, el) {
            mQuery(el).on('click', function () {
                $condition.animate(
                    {'opacity': 0},
                    'fast',
                    function () {
                        mQuery('*[role="tooltip"]').tooltip('destroy');
                        mQuery(this).remove();
                        reorderConditions(actionId);
                    }
                );
            });
        });
    };

    const reorderConditions = function(actionId) {
        // Update the condition numbers so they are ordered correctly when processed server side
        let counter = 0;
        const $container = getConditionsContainer(actionId);
        const $conditions = $container.children('.doi-condition-panel');

        // Name and ID prefixes for this specific action
        const namePrefix = "mauticform[doiActionConditionsConfig][actionConditions][" + actionId + "]";
        const idPrefix = "mauticform_doiActionConditionsConfig_actionConditions_" + actionId;

        $conditions.each(function() {
            const $condition = mQuery(this);

            // Update the condition panel ID
            $condition.attr('id', idPrefix + '_conditions_' + counter);

            // Update glue positioning
            updateConditionPositioning($condition.find('select.glue-select').first());

            // Find all elements within this condition that need renumbering
            $condition.find('[id^="' + idPrefix + '_conditions_"]').each(function() {
                const $element = mQuery(this);
                const id = $element.attr('id');
                const name = $element.attr('name');

                // Skip prototype elements
                if (id && id.includes('__conditionIndex__')) {
                    return true;
                }

                const isProperties = id.includes("_properties_");
                let suffix = id.split(/[_]+/).pop();
                let newName;
                let newId;

                if (name) {
                    if (isProperties) {
                        // Handle properties fields: name[doiActionConditionsConfig][actionConditions][actionId][conditions][counter][properties][filter]
                        const suffixIdMatch = id.match(/_properties_(.*)$/);
                        const suffixNameMatch = name.match(/\[properties\](.*)$/);
                        const suffixId = suffixIdMatch ? suffixIdMatch[1] : suffix;
                        const suffixName = suffixNameMatch ? suffixNameMatch[1] : suffix;

                        newName = namePrefix + '[conditions][' + counter + '][properties]' + suffixName;
                        newId = idPrefix + '_conditions_' + counter + '_properties_' + suffixId;
                    } else {
                        // Handle regular fields: name[doiActionConditionsConfig][actionConditions][actionId][conditions][counter][field]
                        newName = namePrefix + '[conditions][' + counter + '][' + suffix + ']';
                        newId = idPrefix + '_conditions_' + counter + '_' + suffix;

                        // Preserve array notation if present
                        if (name.slice(-2) === '[]') {
                            newName += '[]';
                        }
                    }

                    $element.attr('name', newName);
                } else {
                    // Element has no name attribute, just update ID
                    if (isProperties) {
                        const suffixIdMatch = id.match(/_properties_(.*)$/);
                        const suffixId = suffixIdMatch ? suffixIdMatch[1] : suffix;
                        newId = idPrefix + '_conditions_' + counter + '_properties_' + suffixId;
                    } else {
                        newId = idPrefix + '_conditions_' + counter + '_' + suffix;
                    }
                }

                $element.attr('id', newId);

                // Reinitialize Chosen select for filter dropdowns
                if ($element.is('select') && suffix === 'filter' && isProperties) {
                    Mautic.destroyChosen($element);
                    Mautic.activateChosenSelect($element);
                }

                // Handle radio buttons for date type mode
                if ($element.is(':radio') && id.includes("_dateTypeMode_")) {
                    if ($element.closest('label').hasClass('active')) {
                        $element.click();
                    }
                }
            });

            // Reset panel heading width (something sets it inline)
            $condition.find('.panel-heading').css('width', '');

            ++counter;
        });

        // Update glue visibility (hide first, show rest)
        $conditions.find('.panel-glue').removeClass('hide');
        $conditions.first().find('.panel-glue').addClass('hide');

        // Reinitialize tooltips
        const $tooltips = $conditions.find("*[data-toggle='tooltip']");
        $tooltips.each(function() {
            mQuery(this).tooltip({html: true, container: 'body'});
        });
    };

    const initSortableForConditions = function(actionId) {
        const $container = getConditionsContainer(actionId);

        if (!$container.length) {
            return;
        }

        if ($container.hasClass('ui-sortable')) {
            $container.sortable('destroy');
        }

        let bodyOverflow = {};

        $container.sortable({
            items: '.doi-condition-panel',
            distance: 10,
            helper: function(e, ui) {
                ui.children().each(function() {
                    if (mQuery(this).is(":visible")) {
                        mQuery(this).width(mQuery(this).width());
                    }
                });

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
            cursor: 'move',
            opacity: 0.7,
            tolerance: 'intersect',
            placeholder: 'doi-sortable-placeholder-condition',
            forceHelperSize: true,
            forcePlaceholderSize: true,
            start: function(e, ui) {
                ui.item.data('start-pos', ui.item.index());
                ui.placeholder.height(ui.item.outerHeight());
            },
            stop: function(e, ui) {
                mQuery('body').css(bodyOverflow);

                const startPos = ui.item.data('start-pos');
                const endPos = ui.item.index();

                if (startPos !== endPos) {
                    reorderConditions(actionId);
                }
            }
        });
    };

    const formFieldChangesListener = function() {
        mQuery(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('forms/field/new')) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.mauticContent === 'formField' && response.success === 1) {
                        const $selects = mQuery('select[data-doi-action-id]');

                        $selects.each(function() {
                            const $select = mQuery(this);
                            const infoMessage = $select.data('new-fields-info');

                            if (!infoMessage) {
                                return;
                            }

                            mQuery('option.new-field-notification', $select).remove();
                            const infoOption = mQuery('<option class="new-field-notification" disabled>' + infoMessage + '</option>');
                            const $formOptgroup = mQuery('optgroup[label="form"]', $select);

                            if ($formOptgroup.length) {
                                $formOptgroup.prepend(infoOption);
                            }

                            if ($select.is(':visible') && ($select.hasClass('chosen-select') || $select.data('chosen'))) {
                                $select.trigger('chosen:updated');
                            }
                        });
                    }
                } catch (e) {
                    console.log('Error processing form field response:', e);
                }
            }
        });
    };

    const formNewActionListener = function() {
        mQuery(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('forms-doi/action/new')) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.mauticContent === 'formAction' && response.success === 1) {
                        const actionId = response.actionId;
                        const $action = mQuery('div[data-doi-action="' + actionId + '"]');
                        const $templateSelect = mQuery('[data-onload-callback="onFormDoiActionConditionsBuilder"]')
                            .find('select[data-doi-action-available-conditions-list]');

                        initializeSingleBuilder(actionId, $action, $templateSelect);

                        $action.find('select[data-doi-action-available-conditions-list]').chosen({
                            width: '100%',
                            allow_single_deselect: true
                        });
                    }
                } catch (e) {
                    console.log('Error processing form action response:', e);
                }
            }
        });
    };
    const formDeleteActionListener = function() {
        mQuery(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url && settings.url.includes('forms-doi/action/delete')) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.mauticContent === 'formAction' && response.success === 1) {
                        const urlPath = settings.url.split('?')[0];
                        const urlParts = urlPath.split('/');
                        const actionId = urlParts[urlParts.length - 1];
                        const $action = mQuery('div[data-doi-action="' + actionId + '"]');
                        $action.hide('fast');
                    }
                } catch (e) {
                    console.log('Error processing form action response:', e);
                }
            }
        });
    };

    const getConditionCount = function(actionId) {
        return getConditionsContainer(actionId).children('.doi-condition-panel').length;
    };

    const updateConditionPositioning = function (el) {
        const $el       = mQuery(el);
        const $parentEl = $el.closest('.doi-condition-panel');
        const list      = $parentEl.parent().children('.doi-condition-panel');
        const isFirst = list.index($parentEl) === 0;

        if (isFirst) {
            $el.val('and');
        }

        if ($el.val() === 'and' && !isFirst) {
            $parentEl.addClass('in-group');
        } else {
            $parentEl.removeClass('in-group');
        }
    };

    const createActionConditionsPrototype = function(actionId) {
        const template = `
        <div id="mauticform_doiActionConditionsConfig_actionConditions_${actionId}">
            <input type="hidden" 
                   id="mauticform_doiActionConditionsConfig_actionConditions_${actionId}_actionId"
                   name="mauticform[doiActionConditionsConfig][actionConditions][${actionId}][actionId]" 
                   autocomplete="false" 
                   value="${actionId}">
            <div class="doi-conditions-list" 
                 id="mauticform_doiActionConditionsConfig_actionConditions_${actionId}_conditions"
                 data-conditions-container="">
            </div>
        </div>
    `;
        return template.trim();
    };

    // Public API
    Mautic.doiConvertConditionInput = convertLeadFilterInput;
    Mautic.doiReorderConditions = reorderConditions;
    Mautic.doiUpdateConditionPositioning = updateConditionPositioning;
}(Mautic, mQuery));