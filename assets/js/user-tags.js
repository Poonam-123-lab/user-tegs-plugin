/**
 * User Tags JavaScript
 * 
 * Handles the Select2 initialization and AJAX functionality
 */
jQuery(document).ready(function($) {
    // Initialize Select2 for the user profile page
    if ($('.user-tags-select').length > 0) {
        $('.user-tags-select').select2({
            placeholder: 'Search or select user tags',
            allowClear: true,
            ajax: {
                url: userTagsParams.ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        term: params.term || '',
                        action: 'search_user_tags',
                        nonce: userTagsParams.nonce
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                cache: true
            },
            minimumInputLength: 0
        });
    }
    
  // Initialize Select2 for the users list filter
if ($('.user-tags-filter-select').length > 0) {
    $('.user-tags-filter-select').select2({
        placeholder: 'Filter by User Tag',
        allowClear: true,
        ajax: {
            url: userTagsParams.ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term || '',
                    action: 'search_user_tags',
                    nonce: userTagsParams.nonce
                };
            },
            processResults: function(data) {
                // Add "All User Tags" option if it's not already present
                if (data.length > 0 && data[0].id !== 0) {
                    data.unshift({
                        id: 0,
                        text: 'All User Tags'
                    });
                }
                
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 0
    }).on('change', function() {
        // Auto-submit the form when a selection is made
        // Or just ensure the value is properly set for the form submission
        $('#user_tag_filter').val($(this).val());
    });
}
});