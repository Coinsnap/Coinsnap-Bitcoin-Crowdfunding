// js/popup.js
const checkCFRequiredFieds = (fields) => {
    let valid = true;
    fields.forEach((field) => {
        if (field && field.required && !field.value.trim()) {
            valid = false;
            field.classList.add('error');
            setTimeout(() => {
                field.classList.remove('error');
            }, 3000);
        }
    });
    return valid;
}

const addCrowdfundingPopupListener = (prefix, sufix, type, redirect) => {
    let walletHandler = null;

    const resetPopup = (prefix, sufix) => {
        hideCrowdfundingElementsById(['qr-container', 'blur-overlay', 'payment-loading', 'payment-popup', 'thank-you-popup'], prefix, sufix);
        showCrowdfundingElementById('public-donor-popup', 'flex', prefix, sufix);
        const button = document.getElementById(`${prefix}pay${sufix}`);
        button.disabled = false;
        const payInWalletBtn = document.getElementById(`${prefix}pay-in-wallet${sufix}`);
        if (walletHandler) {
            payInWalletBtn.removeEventListener('click', walletHandler);
            walletHandler = null;
        }
    };


    window.addEventListener("click", function (event) {
        const qrContainer = document.getElementById(`${prefix}qr-container${sufix}`);
        const element = event.target
        if (qrContainer.style.display === 'flex') {
            if (element.classList.contains('close-popup') || (!qrContainer.contains(event.target) && !element.id.includes('pay'))) {
                resetPopup(prefix, sufix)
            }
        }
    });

    document.getElementById(`${prefix}pay${sufix}`).addEventListener('click', async () => {
        const button = document.getElementById(`${prefix}pay${sufix}`);
        button.disabled = true;
        event.preventDefault();
        const honeypot = document.getElementById(`${prefix}email${sufix}`);
        if (honeypot && honeypot.value) {
            return;
        }
        
        const currency = document.getElementById(`${prefix}swap${sufix}`).value?.toUpperCase();
        const amountValue = document.getElementById(`${prefix}amount${sufix}`)?.value;
        const amountField = document.getElementById(`${prefix}amount${sufix}`);
        
        console.log('Amount check');
        if (!amountValue || parseFloat(amountValue) === 0) {
            button.disabled = false;
            addErrorCrowdfundingField(amountField,'');
            return;
        }
        
        let data = {
            action: 'coinsnap_bitcoin_crowdfunding_amount_check',
            apiNonce: coinsnap_bitcoin_crowdfunding_ajax.nonce,
            apiAmount: cleanCrowdfundingAmount(amountValue),
            apiCurrency: currency
        };

        const queryData = new URLSearchParams();
        for ( const key in data ) {
            queryData.set( key, data[ key ] );
        }

        
        const amountCheck = await fetch(coinsnap_bitcoin_crowdfunding_ajax.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cache-Control': 'no-cache'
            },
            body: queryData
        }).catch(error => {
            console.log('Amount check request error' + error);
        });
        
        const responseData = await amountCheck.json();
  
        if(responseData.result === false){
            button.disabled = false;
            addErrorCrowdfundingField(amountField,responseData.error);
            return;
        }
        
        const publicDonor = document.getElementById(`${prefix}qr-container${sufix}`).dataset.publicDonors;
        if (!publicDonor) {
            const publicDonorsPay = document.getElementById(`${prefix}public-donors-pay${sufix}`);
            publicDonorsPay.click();
        }
        showCrowdfundingElementsById(['blur-overlay', 'qr-container'], 'flex', prefix, sufix);
    });

    document.getElementById(`${prefix}public-donors-pay${sufix}`).addEventListener('click', async () => {
        event.preventDefault();
        const publicDonor = document.getElementById(`${prefix}qr-container${sufix}`).dataset.publicDonors;
        var retryId = '';
        const crowdfundingId = document.getElementById('coinsnap-bitcoin-crowdfunding-form').dataset.crowdfundingId;
        const crowdfundName = document.getElementById('coinsnap-bitcoin-crowdfunding-form').dataset.name || '';
        const currencyField = document.getElementById(`${prefix}swap${sufix}`);
        const currency = currencyField.value?.toUpperCase();
        
        const amountField = (currency === 'SATS')? document.getElementById(`${prefix}amount${sufix}`) : document.getElementById(`${prefix}satoshi${sufix}`);
        var amount = (currency === 'SATS')? document.getElementById(`${prefix}amount${sufix}`).value : document.getElementById(`${prefix}satoshi${sufix}`).getAttribute('data-value');
        amount = cleanCrowdfundingAmount(amount);
        
        const amountFiatField = (currency === 'SATS')? document.getElementById(`${prefix}satoshi${sufix}`) : document.getElementById(`${prefix}amount${sufix}`);
        var amountFiat =  (currency === 'SATS')? document.getElementById(`${prefix}satoshi${sufix}`).getAttribute('data-value') : document.getElementById(`${prefix}amount${sufix}`).value;
                
        amountFiat = cleanCrowdfundingAmount(amountFiat);
        
        
        
        const firstNameField = document.getElementById(`${prefix}first-name${sufix}`);
        const lastNameField = document.getElementById(`${prefix}last-name${sufix}`);
        const emailField = document.getElementById(`${prefix}donor-email${sufix}`);
        const streetField = document.getElementById(`${prefix}street${sufix}`);
        const houseNumberField = document.getElementById(`${prefix}house-number${sufix}`);
        const postalCodeField = document.getElementById(`${prefix}postal${sufix}`);
        const cityField = document.getElementById(`${prefix}town${sufix}`);
        const countryField = document.getElementById(`${prefix}country${sufix}`);
        const address = `${streetField?.value ?? ''} ${houseNumberField?.value ?? ''}, ${postalCodeField?.value ?? ''} ${cityField?.value ?? ''}, ${countryField?.value ?? ''}`;
        const customField = document.getElementById(`${prefix}custom${sufix}`);
        const customNameField = document.getElementById(`${prefix}custom-name${sufix}`);
        const customContent = customNameField?.textContent && customField?.value ? `${customNameField.textContent}: ${customField.value}` : '';
        const validForm = !publicDonor || checkCFRequiredFieds([firstNameField, lastNameField, emailField, streetField, houseNumberField, postalCodeField, cityField, countryField, customField]);
        const publicName = document.getElementById(`${prefix}public-name${sufix}`);
        const publicMessage = document.getElementById(`${prefix}public-message${sufix}`);
        
        const metadata = {
            donorName: `${firstNameField?.value ?? ''} ${lastNameField?.value ?? ''}`,
            donorEmail: emailField?.value,
            donorAddress: address !== ' ,  , ' ? address : '',
            donorCustom: customContent,
            formType: type,
            amount: `${amount}`,
            amountFiat: `${amountFiat} ${currency}`,
            publicDonor: publicDonor || 0,
            modal: true,
            crowdfunding_id: crowdfundingId,
            name: publicName?.value,
            message: publicMessage?.value,
            orderNumber: "Donated for Crowdfunding",
            crowdfundName: crowdfundName
        };
        if (!validForm) return;

        showCrowdfundingElementById('payment-loading', 'flex', prefix, sufix);
        hideCrowdfundingElementById('public-donor-popup', prefix, sufix);

        var name = undefined;
        if (type === "Bitcoin Shoutout") {
            const nameField = document.getElementById(`${prefix}name${sufix}`);
            name = nameField?.value || "Anonymous";
        }


        const res = await createCrowdfundingInvoice(amount, amountFiat, currency, '', name, type, false, metadata);

        if (res && res.checkoutLink) {
            // Hide the donor form popup and blur overlay
            hideCrowdfundingElementsById(['qr-container', 'blur-overlay', 'payment-loading'], prefix, sufix);

            // Build the iframe modal (same pattern as booking)
            var existingBackdrop = document.querySelector('.cbcf-modal-backdrop');
            if (existingBackdrop) {
                existingBackdrop.pollingActive = false;
                existingBackdrop.remove();
            }

            var backdrop = document.createElement('div');
            backdrop.className = 'cbcf-modal-backdrop';
            backdrop.style.display = 'flex';
            backdrop.pollingActive = true;

            var modal = document.createElement('div');
            modal.className = 'cbcf-modal';

            var iframe = document.createElement('iframe');
            iframe.className = 'cbcf-modal-iframe';
            iframe.src = res.checkoutLink;

            modal.appendChild(iframe);
            backdrop.appendChild(modal);
            document.body.appendChild(backdrop);

            // Click on the dark backdrop (outside modal) closes it
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) {
                    backdrop.pollingActive = false;
                    backdrop.remove();
                    document.getElementById(`${prefix}pay${sufix}`).disabled = false;
                    resetPopup(prefix, sufix);
                }
            });

            // Poll for payment completion
            var retryNum = 0;
            retryId = res.id;

            const checkPaymentStatus = () => {
                if (!backdrop.pollingActive) return;
                fetch(`/wp-json/crowdfunding/v1/check-payment-status/${res.id}`, {
                        headers: { 'X-WP-Nonce': Coinsnap_Bitcoin_Crowdfunding_sharedData?.nonce || '' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!backdrop.pollingActive) return;
                        if (data.status === 'completed') {
                            backdrop.pollingActive = false;
                            backdrop.remove();
                            resetPopup(prefix, sufix);
                            window.location.reload();
                        } else if (retryNum < 180 && retryId === res.id) {
                            retryNum++;
                            setTimeout(checkPaymentStatus, 1000);
                        }
                    })
                    .catch(error => {
                        console.error('Error checking payment status:', error);
                        retryNum++;
                        if (backdrop.pollingActive && retryId === res.id) {
                            setTimeout(checkPaymentStatus, 5000);
                        }
                    });
            };
            checkPaymentStatus();

        } else {
            console.error('Error creating invoice');
            hideCrowdfundingElementById('payment-loading', prefix, sufix);
            showCrowdfundingElementById('public-donor-popup', 'flex', prefix, sufix);
            document.getElementById(`${prefix}pay${sufix}`).disabled = false;
        }

    });

}
