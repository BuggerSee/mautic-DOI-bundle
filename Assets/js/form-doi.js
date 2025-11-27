(function (Mautic, mQuery){
    Mautic.onFormDoiActionsBuilder = function() {
        Mautic.initHideItemButton('#mauticforms-doi-verified-actions');
    };
    
    Mautic.onFormDoiBuilder = function() {
        const doiSwitch = mQuery('input[name="mauticform[doiConfig][enabled]"]');

        const updateDoiAttribute = function () {
            const isEnabled = mQuery('input[name="mauticform[doiConfig][enabled]"]:checked').val();
            mQuery('#doi-container').attr('data-doi-enabled', isEnabled);
            mQuery('#mauticforms-doi-verified-actions').attr('data-doi-enabled', isEnabled);
        };

        doiSwitch.on('change', updateDoiAttribute);
        updateDoiAttribute();
    };

    Mautic.formDoiActionOnLoad = function (container, response) {
        if (!response.actionHtml) return;

        const { actionHtml, actionId } = response;
        const actionSelector = `#mauticform_doi-action_${actionId}`;
        const $action = mQuery(actionSelector);
        const isNewField = $action.length === 0;
        const $newHtml = mQuery(actionHtml);

        if (isNewField) {
            updateActionHtml($action, actionHtml, isNewField);
            initializeActionFunctionality(actionSelector);
            updateUIAfterAction(isNewField);
        } else {
            const title = $newHtml.find('.action-label').text();
            $action.find('.action-label').text(title);
        }
    };

    function updateActionHtml($action, actionHtml, isNewField) {
        if (isNewField) {
            mQuery('#mauticforms-doi-verified-actions .drop-here').append(actionHtml);
        } else {
            $action.replaceWith(actionHtml);
        }
    }

    function initializeActionFunctionality(actionSelector) {
        const $action = mQuery(actionSelector);

        $action.find("[data-toggle='ajax']").click(function(event) {
            event.preventDefault();
            return Mautic.ajaxifyLink(this, event);
        });

        $action.find("*[data-toggle='tooltip']").tooltip({ html: true });

        $action.find("[data-toggle='ajaxmodal']").on('click.ajaxmodal', function(event) {
            event.preventDefault();
            Mautic.ajaxifyModal(this, event);
        });

        Mautic.initHideItemButton(actionSelector);

        const $verifiedActions = mQuery('#mauticforms-doi-verified-actions');
        $verifiedActions.find('.mauticform-row').off(".mauticform");
        $verifiedActions.find('.mauticform-row').on('dblclick.mauticformactions', function(event) {
            event.preventDefault();
            mQuery(this).find('.btn-edit').first().click();
        });
    }

    function updateUIAfterAction(isNewField) {
        const $actionsPanel = mQuery('#actions-panel');
        if (!$actionsPanel.hasClass('in')) {
            mQuery('a[href="#actions-panel"]').trigger('click');
        }

        if (isNewField) {
            const $wrapper = mQuery('.bundle-main-inner-wrapper');
            $wrapper.scrollTop($wrapper.height());
        }

        mQuery('#form-doi-action-placeholder').remove();
    }
}(Mautic, mQuery));