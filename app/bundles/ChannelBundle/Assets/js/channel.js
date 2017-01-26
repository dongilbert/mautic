Mautic.messagesOnLoad = function(container) {
    mQuery(container + ' .sortable-panel-wrapper .modal').each(function() {
      // Move modals outside of the wrapper
      mQuery(this).closest('.panel').append(mQuery(this));
    });

    mQuery(container+ ' *[data-onload]').each(function() {
        var onloadMethod = mQuery(this).attr('data-onload');
        var container    = '#'+mQuery(this).attr('id');
        if (onloadMethod && typeof Mautic[onloadMethod + "OnLoad"] == 'function') {
            Mautic[onloadMethod + "OnLoad"](container);
        }
    });
};

Mautic.toggleChannelFormDisplay = function (el, channel) {
    Mautic.toggleTabPublished(el);

    if (mQuery(el).val() === "1" && mQuery(el).prop('checked')) {
        mQuery('#message_channels_form_' + channel).removeClass('hide')
    } else {
        mQuery('#message_channels_form_' + channel).addClass('hide');
    }
};