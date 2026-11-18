(function ($) {
  // Wait until the DOM is fully loaded
  $(document).ready(function () {
    const $providerSelector = $('#provider');
    const $coinsnapWrapper = $('#coinsnap-settings-wrapper');
    const $btcpayWrapper = $('#btcpay-settings-wrapper');

    // API calls and webhook registration happen on the server only.
    function toggleProviderSettings() {
      if (!$providerSelector || !$providerSelector.length) {
        return;
      }
      const selectedProvider = $providerSelector.val();
      $coinsnapWrapper.toggle(selectedProvider === 'coinsnap');
      $btcpayWrapper.toggle(selectedProvider === 'btcpay');
    }

    toggleProviderSettings();

    $providerSelector.on('change', toggleProviderSettings);

    //  Crownfunding list
    function toggleDonorFields() {
                    if ($('input[name="coinsnap_bitcoin_crowdfunding_collect_donor_info"]').is(':checked')) {
                        $('#donor-info-fields').show();
                    } else {
                        $('#donor-info-fields').hide();
                    }
                }

    function toggleShoutoutShortcode() {
                    if ($('input[name="coinsnap_bitcoin_crowdfunding_shoutout"]').is(':checked')) {
                        $('#shoutout-shortcode-row').show();
                    } else {
                        $('#shoutout-shortcode-row').hide();
                    }
                }

    $('input[name="coinsnap_bitcoin_crowdfunding_collect_donor_info"]').change(toggleDonorFields);
    $('input[name="coinsnap_bitcoin_crowdfunding_shoutout"]').change(toggleShoutoutShortcode);

    toggleDonorFields();
    toggleShoutoutShortcode();
    
    if($('#coinsnap_bitcoin_crowdfunding_default_currency').length){
        
        setStep();
        $('#coinsnap_bitcoin_crowdfunding_default_currency').change(
            function(){
                setStep();
            }    
        );
        
    }
    
    $('#coinsnap_bitcoin_crowdfunding_btcpay_wizard_button').click(function(e) {
        e.preventDefault();
        const host = $('#btcpay_url').val();
	if (isCrowdfundingValidUrl(host)) {
            let data = {
                'action': 'coinsnap_bitcoin_crowdfunding_btcpay_apiurl_handler',
                'host': host,
                'apiNonce': coinsnap_bitcoin_crowdfunding_ajax.nonce
            };
            
            $.post(coinsnap_bitcoin_crowdfunding_ajax.ajax_url, data, function(response) {
                if (response.data.url) {
                    window.location = response.data.url;
		}
            }).fail( function() {
		alert('Error processing your request. Please make sure to enter a valid BTCPay Server instance URL.')
            });
	}
        else {
            alert('Please enter a valid url including https:// in the BTCPay Server URL input field.')
        }
    });
    
    if($('.coinsnapConnectionStatus').length){
        
        
        let ajaxurl = coinsnap_bitcoin_crowdfunding_ajax.ajax_url;
        let data = {
            action: 'coinsnap_bitcoin_crowdfunding_connection_handler',
            apiNonce: coinsnap_bitcoin_crowdfunding_ajax.nonce,
            apiPost: coinsnap_bitcoin_crowdfunding_ajax.post
        };

        jQuery.post( ajaxurl, data, function( response ){

            connectionCheckResponse = $.parseJSON(response);
            let resultClass = (connectionCheckResponse.result === true)? 'success' : 'error';
            $('.coinsnapConnectionStatus').html('<span class="'+resultClass+'">'+ connectionCheckResponse.message +'</span>');
            
        });
    }
    
  });
  
  function setStep(){
        let step = 0.01;
        let currency = $('#coinsnap_bitcoin_crowdfunding_default_currency').val();
        if(currency === 'RUB' || currency === 'JPY' || currency === 'SATS'){
            step = 1;
        }
        $('.coinsnap_bitcoin_crowdfunding_amount').attr('step', step);
    }
  
  function isCrowdfundingValidUrl(serverUrl) {
        if(serverUrl.indexOf('http') > -1){
            try {
                const url = new URL(serverUrl);
                if (url.protocol !== 'https:' && url.protocol !== 'http:') {
                    return false;
                }
            }
            catch (e) {
                return false;
            }
            return true;
        }
        else {
            return false;
        }
    }

})(jQuery);
