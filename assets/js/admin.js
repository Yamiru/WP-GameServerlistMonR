
(function($) {
    'use strict';

    const GSLMAdmin = {
        
        init: function() {
            this.bindEvents();
            this.checkServerStatuses();
        },
        
        bindEvents: function() {
            $('#clear_all_cache').on('click', this.clearCache);
        },
        
        clearCache: function(e) {
            e.preventDefault();
            
            if (!confirm('Clear all server cache?')) {
                return;
            }
            
            $.ajax({
                url: gslm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'gslm_clear_cache',
                    nonce: gslm_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    }
                }
            });
        },
        
        checkServerStatuses: function() {
            $('.gslm-status-check').each(function() {
                const $element = $(this);
                const serverId = $element.data('server-id');
                
                $.ajax({
                    url: gslm_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gslm_get_server_status',
                        nonce: gslm_admin.nonce,
                        server_id: serverId
                    },
                    success: function(response) {
                        if (response.success && response.data.online) {
                            $element.html('<span style="color: green;">Online</span>');
                        } else {
                            $element.html('<span style="color: red;">Offline</span>');
                        }
                    },
                    error: function() {
                        $element.html('<span style="color: gray;">Unknown</span>');
                    }
                });
            });
        }
    };
    
    $(document).ready(function() {
        GSLMAdmin.init();
    });
    
})(jQuery);
