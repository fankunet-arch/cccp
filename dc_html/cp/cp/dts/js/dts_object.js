$(document).ready(function() {
    // Toast 提示
    if ($('#feedback-bar').length) {
        setTimeout(function() {
            $('#feedback-bar').fadeOut();
        }, 3000);
    }
});