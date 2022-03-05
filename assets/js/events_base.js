function registerLoadHandler(fn) {
    document.documentElement.addEventListener('turbo:load', fn);
}


registerLoadHandler(function() {
    /**
     * Register the button, to jump to the top of the page.
     */
    $(document).on("ajaxUI:start", function registerJumpToTop() {
        $(window).scroll(function () {
            if ($(this).scrollTop() > 50) {
                $('#back-to-top').fadeIn();
            } else {
                $('#back-to-top').fadeOut();
            }
        });
        // scroll body to 0px on click
        $('#back-to-top').click(function () {
            $('#back-to-top').tooltip('hide');
            $('body,html').animate({
                scrollTop: 0
            }, 800);
            return false;
        }).tooltip();
    });
})