$(document).ready(function() {
    console.log('Document is ready...');
    $(".select2").select2();

    // Global fix for modal interaction issues
    // This ensures all modals are properly clickable and interactive
    $(document).on('show.bs.modal', '.modal', function() {
        console.log('Modal opening:', $(this).attr('id'));

        // Force proper z-index and pointer events
        var $modal = $(this);

        setTimeout(function() {
            var $backdrop = $('.modal-backdrop');

            // AGGRESSIVE FIX: Remove any blocking overlays
            $backdrop.css({
                'z-index': '1040',
                'pointer-events': 'none',  // Make backdrop non-blocking
                'background-color': 'rgba(0, 0, 0, 0.8)'
            });

            // Ensure modal is on top and fully interactive
            $modal.css({
                'z-index': '1050',
                'pointer-events': 'auto',
                'position': 'fixed'
            });

            // Make modal dialog and content explicitly interactive
            $modal.find('.modal-dialog').css({
                'pointer-events': 'auto',
                'position': 'relative',
                'z-index': '1051'
            });

            $modal.find('.modal-content').css({
                'pointer-events': 'auto',
                'position': 'relative',
                'z-index': '1052'
            });

            // Ensure ALL modal children are clickable
            $modal.find('*').css('pointer-events', 'auto');

            // Explicitly enable form controls
            $modal.find('input, button, select, textarea, a').css({
                'pointer-events': 'auto',
                'cursor': 'text'
            });

            $modal.find('button, a, .close').css({
                'cursor': 'pointer'
            });

            // Disable pointer events on body content to prevent click-through
            $('body').addClass('modal-open');
            $('body > *:not(.modal):not(.modal-backdrop)').css('pointer-events', 'none');

            console.log('Modal CSS fixed - backdrop non-blocking, modal fully interactive');
        }, 50);  // Increased timeout to ensure DOM is ready
    });

    $(document).on('shown.bs.modal', '.modal', function() {
        console.log('Modal shown:', $(this).attr('id'));

        // Additional fix after modal is fully shown
        var $modal = $(this);
        $modal.css('pointer-events', 'auto');
        $modal.find('*').css('pointer-events', 'auto');

        // Focus first input
        var $firstInput = $modal.find('input:visible:first');
        if ($firstInput.length) {
            $firstInput.focus();
            console.log('Focused first input');
        }
    });

    $(document).on('hidden.bs.modal', '.modal', function() {
        console.log('Modal hidden:', $(this).attr('id'));
        // Re-enable pointer events on body content
        $('body > *').css('pointer-events', '');
    });

    // Additional fix: Ensure clicks on modal inputs work
    $(document).on('mousedown', '.modal input, .modal button, .modal .close', function(e) {
        console.log('Click detected on:', $(this).attr('name') || $(this).attr('class'));
        e.stopPropagation();
    });
});