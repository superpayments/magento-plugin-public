require([
    'jquery',
], function ($) {
    $(document).ready(function() {
        $('#super_payments_validate_button').click(function(event) {
            event.preventDefault();

            let url = $(this).data('url');

            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    if (response.statusCode === 200 || response.statusCode === 201) {
                        $('#super_payments_validate_button').text('Validation Successful').css('background-color', 'green').css('color', 'white');
                    } else {
                        $('#super_payments_validate_button').text('Validation Failed').css('background-color', 'red').css('color', 'white');
                    }
                }
            });
        });
    });
});

