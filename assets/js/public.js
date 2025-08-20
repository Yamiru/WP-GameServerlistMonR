(function($) {
    'use strict';

    const GSLMPublic = {
        
        refreshTimers: {},
        debugMode: false, // Set to true for debugging
        
        init: function() {
            this.loadAllServers();
            this.bindEvents();
            this.startAutoRefresh();
        },
        
        bindEvents: function() {
            $(document).on('click', '.gslm-server-address', this.copyAddress.bind(this));
            $(document).on('click', '.gslm-connect-btn', this.handleConnect.bind(this));
        },
        
        loadAllServers: function() {
            $('.gslm-server-row').each(function() {
                const serverId = $(this).data('server-id');
                if (serverId) {
                    GSLMPublic.loadServerStatus(serverId);
                }
            });
        },
        
        startAutoRefresh: function() {
            $('.gslm-server-row').each(function() {
                const $row = $(this);
                const serverId = $row.data('server-id');
                const refreshInterval = parseInt($row.data('refresh'));
                
                if (serverId && refreshInterval > 0) {
                    // Clear existing timer if any
                    if (GSLMPublic.refreshTimers[serverId]) {
                        clearInterval(GSLMPublic.refreshTimers[serverId]);
                    }
                    
                    // Set new timer
                    GSLMPublic.refreshTimers[serverId] = setInterval(function() {
                        GSLMPublic.loadServerStatus(serverId);
                    }, refreshInterval * 1000);
                }
            });
        },
        
        loadServerStatus: function(serverId) {
            const $row = $('.gslm-server-row[data-server-id="' + serverId + '"]');
            const $statusIndicator = $row.find('.gslm-status-indicator');
            
            // Set loading state
            $statusIndicator.attr('data-status', 'checking');
            
            $.ajax({
                url: gslm_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'gslm_get_server_status',
                    nonce: gslm_public.nonce,
                    server_id: serverId
                },
                success: function(response) {
                    if (response.success) {
                        GSLMPublic.updateServerDisplay(serverId, response.data);
                    } else {
                        GSLMPublic.showServerError(serverId, response.data ? response.data.message : 'Unknown error');
                    }
                },
                error: function(xhr, status, error) {
                    GSLMPublic.showServerError(serverId, 'Connection failed: ' + error);
                }
            });
        },
        
        updateServerDisplay: function(serverId, data) {
            const $row = $('.gslm-server-row[data-server-id="' + serverId + '"]');
            const $statusIndicator = $row.find('.gslm-status-indicator');
            const isDiscord = $row.hasClass('server-type-discord');
            
            if (this.debugMode) {
                console.log('Server ' + serverId + ' data:', data);
            }
            
            // Check if server is online - handle both fields
            const isOnline = (data.online === true || data.online === 1 || 
                             data.gq_online === true || data.gq_online === 1);
            
            if (isOnline) {
                // Server is ONLINE
                $statusIndicator.attr('data-status', 'online');
                $row.attr('data-status', 'online').removeClass('server-offline');
                
                // Update server name
                this.updateServerName($row, data);
                
                // Update game type (not for Discord)
                if (!isDiscord) {
                    this.updateGameType($row, data);
                    this.updateMap($row, data);
                }
                
                // Update player count - always show for online servers
                this.updatePlayerCount($row, data, isDiscord);
                
                // Update connect button - always show for online servers
                this.updateConnectButton($row, data);
                
                // Make sure elements are visible
                $row.find('.gslm-players').show();
                if ($row.find('.gslm-connect-btn').attr('href') !== '#') {
                    $row.find('.gslm-connect-btn').show();
                }
                
            } else {
                // Server is OFFLINE
                $statusIndicator.attr('data-status', 'offline');
                $row.attr('data-status', 'offline').addClass('server-offline');
                
                // Keep original name when offline
                this.restoreOriginalName($row);
                
                // Keep original game type when offline
                if (!isDiscord) {
                    this.restoreOriginalGameType($row);
                }
                
                // Hide map info
                $row.find('.gslm-map-info').hide();
                
                // Hide connect button for offline servers
                $row.find('.gslm-connect-btn').hide();
                
                // Show offline status in player count
                const $players = $row.find('.gslm-players');
                const $playersCount = $row.find('.gslm-players-count');
                $playersCount.html('<span style="color: #ef4444; font-weight: 600;">OFFLINE</span>');
                $players.show(); // Show players div with offline status
            }
        },
        
        updateServerName: function($row, data) {
            const $serverName = $row.find('.gslm-server-name');
            const originalName = $serverName.data('original');
            
            // Priority: gq_hostname > hostname > original > "Server"
            let nameToDisplay = 'Server';
            
            if (data.gq_hostname && data.gq_hostname !== '' && data.gq_hostname !== 'Server') {
                nameToDisplay = data.gq_hostname;
            } else if (data.hostname && data.hostname !== '' && data.hostname !== 'Server') {
                nameToDisplay = data.hostname;
            } else if (originalName && originalName !== '') {
                nameToDisplay = originalName;
            }
            
            $serverName.text(nameToDisplay);
        },
        
        restoreOriginalName: function($row) {
            const $serverName = $row.find('.gslm-server-name');
            const originalName = $serverName.data('original');
            
            if (originalName && originalName !== '') {
                $serverName.text(originalName);
            } else {
                $serverName.text('Server');
            }
        },
        
        updateGameType: function($row, data) {
            const $gametype = $row.find('.gslm-gametype');
            
            if ($gametype.length) {
                if (data.gq_gametype && data.gq_gametype !== '') {
                    $gametype.text(data.gq_gametype);
                } else if (data.gametype && data.gametype !== '') {
                    $gametype.text(data.gametype);
                }
            }
        },
        
        restoreOriginalGameType: function($row) {
            const $gametype = $row.find('.gslm-gametype');
            const originalType = $gametype.data('original');
            
            if (originalType && originalType !== '') {
                $gametype.text(originalType);
            }
        },
        
        updatePlayerCount: function($row, data, isDiscord) {
            const $players = $row.find('.gslm-players');
            const $playersCount = $row.find('.gslm-players-count');
            
            // Get player counts from various possible fields
            let currentPlayers = 0;
            let maxPlayers = 0;
            
            // Try different field names for current players
            if (typeof data.gq_numplayers !== 'undefined') {
                currentPlayers = parseInt(data.gq_numplayers) || 0;
            } else if (typeof data.players !== 'undefined') {
                currentPlayers = parseInt(data.players) || 0;
            } else if (typeof data.numplayers !== 'undefined') {
                currentPlayers = parseInt(data.numplayers) || 0;
            }
            
            // Try different field names for max players
            if (typeof data.gq_maxplayers !== 'undefined') {
                maxPlayers = parseInt(data.gq_maxplayers) || 0;
            } else if (typeof data.max_players !== 'undefined') {
                maxPlayers = parseInt(data.max_players) || 0;
            } else if (typeof data.maxplayers !== 'undefined') {
                maxPlayers = parseInt(data.maxplayers) || 0;
            }
            
            // Format player text
            let playerText = currentPlayers + '/' + maxPlayers;
            
            // Add appropriate label
            if (isDiscord) {
                // Discord shows online/total members
                playerText = currentPlayers + ' online / ' + maxPlayers + ' members';
            } else {
                playerText = playerText + ' players';
            }
            
            $playersCount.text(playerText);
            $players.fadeIn();
        },
        
        updateMap: function($row, data) {
            const $mapInfo = $row.find('.gslm-map-info');
            const $mapName = $row.find('.gslm-map-name');
            
            // Try different field names for map
            let mapName = '';
            
            if (data.gq_mapname && data.gq_mapname !== '') {
                mapName = data.gq_mapname;
            } else if (data.map && data.map !== '') {
                mapName = data.map;
            } else if (data.mapname && data.mapname !== '') {
                mapName = data.mapname;
            }
            
            if (mapName) {
                $mapName.text(mapName);
                $mapInfo.fadeIn();
            } else {
                $mapInfo.fadeOut();
            }
        },
        
        updateConnectButton: function($row, data) {
            if (data.connect_link && data.connect_link !== '#') {
                const $connectBtn = $row.find('.gslm-connect-btn');
                $connectBtn.attr('href', data.connect_link);
                
                // Set target based on link type
                if (data.connect_link.startsWith('http')) {
                    $connectBtn.attr('target', '_blank');
                } else {
                    $connectBtn.removeAttr('target');
                }
                
                $connectBtn.fadeIn();
            }
        },
        
        showServerError: function(serverId, errorMessage) {
            const $row = $('.gslm-server-row[data-server-id="' + serverId + '"]');
            const $statusIndicator = $row.find('.gslm-status-indicator');
            
            if (this.debugMode) {
                console.error('Server ' + serverId + ' error:', errorMessage);
            }
            
            // Set offline status
            $statusIndicator.attr('data-status', 'offline');
            $row.attr('data-status', 'offline');
            
            // Keep original name on error
            this.restoreOriginalName($row);
            
            // Don't show ERROR text, show OFFLINE instead
            const $players = $row.find('.gslm-players');
            const $playersCount = $row.find('.gslm-players-count');
            $playersCount.html('<span style="color: #ef4444; font-weight: 600;">OFFLINE</span>');
            $players.fadeIn();
            
            // Hide map and connect button
            $row.find('.gslm-map-info').hide();
            $row.find('.gslm-connect-btn').hide();
        },
        
        copyAddress: function(e) {
            e.preventDefault();
            const $element = $(e.currentTarget);
            const address = $element.data('copy');
            
            if (!address) return;
            
            // Modern clipboard API with fallback
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(address).then(() => {
                    this.showCopyFeedback($element);
                }).catch(() => {
                    this.fallbackCopy(address, $element);
                });
            } else {
                this.fallbackCopy(address, $element);
            }
        },
        
        fallbackCopy: function(text, $element) {
            const $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                document.execCommand('copy');
                this.showCopyFeedback($element);
            } catch(err) {
                console.error('Failed to copy:', err);
            }
            
            $temp.remove();
        },
        
        showCopyFeedback: function($element) {
            const originalText = $element.text();
            $element.text('Copied!');
            $element.css({
                'background': 'rgba(16, 185, 129, 0.2)',
                'color': '#059669'
            });
            
            setTimeout(function() {
                $element.text(originalText);
                $element.css({
                    'background': '',
                    'color': ''
                });
            }, 1500);
        },
        
        handleConnect: function(e) {
            const $btn = $(e.currentTarget);
            const href = $btn.attr('href');
            
            if (!href || href === '#') {
                e.preventDefault();
                return false;
            }
            
            // For HTTP links, let browser handle it
            if (href.startsWith('http')) {
                // Link will open in new tab due to target="_blank"
                return true;
            }
            
            // For protocol links (steam://, minecraft://, etc.)
            e.preventDefault();
            
            // Show connecting feedback
            const originalText = $btn.text();
            $btn.text('Connecting...');
            
            // Open protocol link
            window.location.href = href;
            
            // Restore button text after delay
            setTimeout(function() {
                $btn.text(originalText);
            }, 2000);
            
            return false;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        GSLMPublic.init();
        
        // Auto refresh every 30 seconds for servers without individual refresh
        setInterval(function() {
            $('.gslm-server-row[data-refresh="0"]').each(function() {
                const serverId = $(this).data('server-id');
                if (serverId) {
                    GSLMPublic.loadServerStatus(serverId);
                }
            });
        }, 30000);
    });
    
    // Public API
    window.GSLMPublic = GSLMPublic;
    
})(jQuery);
