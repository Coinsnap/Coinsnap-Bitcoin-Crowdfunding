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
        }).catch(() => null);
        // A PHP notice in front of the JSON, or an HTML error page, must not leave the button stuck.
        let responseData = null;
        if (amountCheck) {
            try {
                responseData = await amountCheck.json();
            } catch (e) {
                responseData = null;
            }
        }
        if (!responseData || typeof responseData !== 'object') {
            button.disabled = false;
            addErrorCrowdfundingField(amountField, crowdfundingText('paymentError'));
            return;
        }

        if(responseData.result === false){
            button.disabled = false;
            addErrorCrowdfundingField(amountField, responseData.error || crowdfundingText('paymentError'));
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
        
        const donor = {
            first_name: firstNameField?.value ?? '',
            last_name: lastNameField?.value ?? '',
            email: emailField?.value ?? '',
            street: streetField?.value ?? '',
            house_number: houseNumberField?.value ?? '',
            postal: postalCodeField?.value ?? '',
            town: cityField?.value ?? '',
            country: countryField?.value ?? '',
            custom: customField?.value ?? '',
            public_name: publicName?.value ?? '',
            public_message: publicMessage?.value ?? ''
        };
        if (!validForm) return;

        showCrowdfundingElementById('payment-loading', 'flex', prefix, sufix);
        hideCrowdfundingElementById('public-donor-popup', prefix, sufix);

        const res = await createCrowdfundingInvoice(crowdfundingId, amount, donor);

        if (res && res.error) {
            // Close the popup so the message next to the amount can be read.
            resetPopup(prefix, sufix);
            addErrorCrowdfundingField(document.getElementById(`${prefix}amount${sufix}`), res.error);
            return;
        }

        if (res) {
            const target = crowdfundingPaymentTarget(res);
            const isLightning = target.kind === 'lightning';
            const qrLightning = isLightning ? target.text : '';
            const qrBitcoin = res.onchainAddress || '';
            const checkoutLink = res.checkoutLink || '';

            if (qrBitcoin) {
                showCrowdfundingElementsById(['btc-wrapper', 'qr-btc-container'], 'flex', prefix, sufix)
            }
            if (isLightning) {
                showCrowdfundingElementById('lightning-wrapper', 'block', prefix, sufix);
                showCrowdfundingElementById('qr-lightning-container', 'flex', prefix, sufix);
            } else {
                hideCrowdfundingElementsById(['lightning-wrapper', 'qr-lightning-container'], prefix, sufix);
            }

            // Hide spinner and show qr code stuff
            if (target.qr) {
                showCrowdfundingElementById('qrCode', 'block', prefix, sufix);
                showCrowdfundingElementById('qrCodeBtc', 'block', prefix, sufix);
            } else {
                hideCrowdfundingElementsById(['qrCode', 'qrCodeBtc'], prefix, sufix);
            }
            showCrowdfundingElementById('qr-summary', 'flex', prefix, sufix);
            if (target.link) {
                showCrowdfundingElementById('pay-in-wallet', 'flex', prefix, sufix);
            } else {
                hideCrowdfundingElementById('pay-in-wallet', prefix, sufix);
            }
            const openCheckout = document.getElementById(`${prefix}open-checkout${sufix}`);
            if (openCheckout) {
                if (checkoutLink) {
                    openCheckout.setAttribute('href', checkoutLink);
                    openCheckout.style.display = 'flex';
                } else {
                    openCheckout.removeAttribute('href');
                    openCheckout.style.display = 'none';
                }
            }
            hideCrowdfundingElementById('payment-loading', prefix, sufix);
            showCrowdfundingElementById('payment-popup', 'flex', prefix, sufix);
            
            // Update actuall data
            if (target.qr) {
                document.getElementById(`${prefix}qrCode${sufix}`).src = target.qr;
            } else {
                document.getElementById(`${prefix}qrCode${sufix}`).removeAttribute('src');
            }
            document.getElementById(`${prefix}qr-lightning${sufix}`).textContent = qrLightning ? `${qrLightning.substring(0, 20)}...${qrLightning.slice(-15)}` : '';
            document.getElementById(`${prefix}qr-btc${sufix}`).textContent = `${qrBitcoin.substring(0, 20)}...${qrBitcoin.slice(-15)}`;
            document.getElementById(`${prefix}qr-amount${sufix}`).textContent = crowdfundingText('amountLabel').replace('%s', res.amount);

            // Copy address functionallity 
            const copyLightning = document.querySelector(`#${prefix}qr-lightning-container${sufix} .qr-copy-icon`);
            const copyBtc = document.querySelector(`#${prefix}qr-btc-container${sufix} .qr-copy-icon`);
            copyLightning.onclick = () => { navigator.clipboard.writeText(qrLightning); };
            copyBtc.onclick = () => { navigator.clipboard.writeText(qrBitcoin); };

            // The other currency next to the sats: EUR on a SATS campaign, the campaign's currency otherwise.
            const fiatCurrency = (currency === 'SATS') ? 'EUR' : currency;
            const fiatField = document.getElementById(`${prefix}qr-fiat${sufix}`);
            if (Number.isFinite(amountFiat) && amountFiat > 0) {
                fiatField.textContent = `≈ ${amountFiat} ${fiatCurrency}`;
                fiatField.style.display = 'block';
            } else {
                fiatField.textContent = '';
                fiatField.style.display = 'none';
            }
            const payInWallet = document.getElementById(`${prefix}pay-in-wallet${sufix}`);
            if (target.link) {
                payInWallet.setAttribute('href', target.link);
            } else {
                payInWallet.removeAttribute('href');
            }

            // Reset retry counter
            var retryNum = 0;
            retryId = res.id;

            const checkPaymentStatus = () => {
                fetch(`${Coinsnap_Bitcoin_Crowdfunding_sharedData.statusUrl}${encodeURIComponent(res.id)}`)
                    .then(response => response.json())
                    .then(data => {
                        const qrContainer = document.getElementById(`${prefix}qr-container${sufix}`);

                        if (data.status === 'completed') {
                            showCrowdfundingElementById('thank-you-popup', 'flex', prefix, sufix);
                            hideCrowdfundingElementById('payment-popup', prefix, sufix);
                            setTimeout(() => {
                                resetPopup(prefix, sufix);
                                window.location.reload();
                            }, 2000);

                        } else if (qrContainer.style.display !== 'flex') {
                            retryId = '';
                        }
                        else if (retryNum < 180 && retryId === res.id) {
                            retryNum++;
                            checkPaymentStatus();
                        } else {
                            //TODO Invoice expired
                        }
                    })
                    .catch(() => {
                        retryNum++;
                        if (retryId == res.id) {
                            setTimeout(checkPaymentStatus, 5000);
                        }
                    });
            }
            checkPaymentStatus();

        }
        else {
            resetPopup(prefix, sufix);
            addErrorCrowdfundingField(document.getElementById(`${prefix}amount${sufix}`), crowdfundingText('paymentError'));
        }

    });

}
