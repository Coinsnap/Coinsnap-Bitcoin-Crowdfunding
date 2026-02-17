function deleteCookie(name) {
    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/;';
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
}

function setCookie(name, value, minutes) {
    const d = new Date();
    d.setTime(d.getTime() + (minutes * 60 * 1000));
    const expires = "expires=" + d.toUTCString();
    document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

const addErrorCrowdfundingField = (field,message) => {
    field.classList.add('error');
    if(message !== ''){
        if(jQuery(field).next('.crowdfunding-field-error').length){
            jQuery(field).next('.crowdfunding-field-error').html(message);
        }
        else {
            jQuery(field).after('<span class="crowdfunding-field-error">'+message+'</span>');
        }
        
    }
    removeCrowdfundingBorderOnFocus(field, field);
};

const removeCrowdfundingBorderOnFocus = (field1, field2) => {
    field1.addEventListener('focus', function () {
        field2.classList.remove('error');
        jQuery(field1).next('.crowdfunding-field-error').remove();
        jQuery(field2).next('.crowdfunding-field-error').remove();
    });
};

const handleCrowdfundingButtonClick = (buttonId, honeypotId, amountId, satoshiId, messageId, lastInputCurrency, name) => {
    event.preventDefault();

    const button = document.getElementById(buttonId);
    button.disabled = true;

    const honeypot = document.getElementById(honeypotId);
    if (honeypot && honeypot.value){
        return;
    }

    const amountField = document.getElementById(amountId);
    const fiatAmount = cleanCrowdfundingAmount(amountField.value);

    let satoshiField = null;
    let satsAmount = null;

    if (satoshiId) {
        satoshiField = document.getElementById(satoshiId);
        satsAmount = cleanCrowdfundingAmount(satoshiField.value);
    }

    if (isNaN(fiatAmount) && isNaN(satsAmount)) {
        button.disabled = false;
        addErrorCrowdfundingField(amountField,'');
        if (satoshiField) {
            addErrorCrowdfundingField(satoshiField,'');
            removeCrowdfundingBorderOnFocus(satoshiField, amountField);
            removeCrowdfundingBorderOnFocus(amountField, satoshiField);
        }
        return;
    }

    const messageField = document.getElementById(messageId);
    const message = messageField ? messageField.value : "";

    const currency = lastInputCurrency.toUpperCase();
    const amount = currency === 'SATS' ? satsAmount : fiatAmount;

    if (!isNaN(amount) && amount > 0) {
        const type = name ? 'Shoutout Donation' : 'Donation Button';
        createCrowdfundingInvoice(amount, fiatAmount, lastInputCurrency, message, name, type, );
    } else {
        button.disabled = false;
    }
};

const handleCrowdfundingButtonClickMulti = (buttonId, honeypotId, amountId, messageId, lastInputCurrency, name) => {
    const button = document.getElementById(buttonId);
    button.disabled = true;
    event.preventDefault();
    const honeypot = document.getElementById(honeypotId);
    if (honeypot && honeypot.value) {
        event.preventDefault();
        return;
    }
    const amountField = document.getElementById(amountId);
    const fiatAmount = cleanCrowdfundingAmount(amountField.value);

    if (!fiatAmount) {
        button.disabled = false;
        addErrorCrowdfundingField(amountField,'');
        event.preventDefault();
        return;
    }

    const messageField = document.getElementById(messageId);
    const message = messageField ? messageField.value : "";
    const currency = lastInputCurrency.toUpperCase();
    const amount = fiatAmount;
    if (amount) {
        createCrowdfundingInvoice(amount, fiatAmount, currency, message, name, 'Multi Amount Donation');
    }
};

async function generateQRCodeCrowdfundingDataURL(text) {
    try {
        console.log('Invoice URL: ' + encodeURIComponent(text));
        const response = await fetch(`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(text)}`);
        const blob = await response.blob();
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.readAsDataURL(blob);
        });
    } catch (error) {
        console.error('Error generating QR code:', error);
        return null;
    }
}

const createActualCrowdfundingInvoice = async (amount, message, lastInputCurrency, name, coinsnap, type, redirect, metadata) => {
    deleteCookie('coinsnap_invoice_crowdfunding');
    
    var provider = (coinsnap === true)? 'coinsnap' : 'btcpay';
    var orderId = 'CRFN_' + (Date.now()).toString(36);
    
    if(provider === 'btcpay'){
        metadata.orderId = orderId;
    }

    const requestData = {
        amount: amount,
        currency: lastInputCurrency,
        buyerEmail: metadata.donorEmail,
        redirectAutomatically: true,
        checkout: {
            redirectAutomatically: true
        },
        orderId: orderId,
        walletMessage: message,
        metadata: {
            orderNumber: message,
            type: type,
            name: name,
            ...metadata
        }
    };
    
    requestData.provider = provider;
    
    if (type === 'Donation Crowdfunding') {
        requestData.redirectUrl = requestData.checkout.redirectUrl = Coinsnap_Bitcoin_Crowdfunding_sharedData?.redirectUrl || window.location.href;        
    }
    
    if (window.location.href.includes("localhost")) {
        requestData.redirectUrl = requestData.checkout.redirectUrl = "https://coinsnap.io";
    }

    if (provider === 'coinsnap') {
        requestData.referralCode = 'D2SXIW';
    }

    const url = (provider === 'coinsnap')?
        `https://app.coinsnap.io/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData?.coinsnapStoreId}/invoices`
        : `${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayStoreId}/invoices`;

    const headers = (provider === 'coinsnap')
        ? {
            'x-api-key': Coinsnap_Bitcoin_Crowdfunding_sharedData?.coinsnapApiKey,
            'Content-Type': 'application/json'
        }
        : {
            'Authorization': `token ${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayApiKey}`,
            'Content-Type': 'application/json'
        };

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(requestData)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        var responseData = await response.json();

        const invoiceCookieData = {
            id: responseData.id,
            amount: amount,
            currency: lastInputCurrency,
            checkoutLink: responseData.checkoutLink,
            message: message,
            name: name
        };

        setCookie('coinsnap_invoice_crowdfunding', JSON.stringify(invoiceCookieData), 15);

        if (provider === 'btcpay') {
            const url = `${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayStoreId}/invoices/${responseData.id}/payment-methods`;
            const response2 = await fetch(url, {
                method: 'GET',
                headers: headers
            });
            const responseData2 = await response2.json();
            const paymentLink = responseData2[0].paymentLink;
            console.log('Payment Link:', paymentLink);
            responseData.lightningInvoice = paymentLink?.replace('lightning:', '');
            responseData.onchainAddress = '';

            // Generate QR code image from lightning invoice
            const qrCodeImage = await generateQRCodeCrowdfundingDataURL(paymentLink);
            responseData.qrCodes = {
                lightningQR: qrCodeImage || paymentLink
            };
        }

        if (redirect) {
            window.location.href = responseData.checkoutLink;
        }

        return responseData;
    } catch (error) {
        console.error('Error creating invoice:', error);
        return null;
    }
};

const checkCrowdfundingInvoiceStatus = async (invoiceId, amount, message, lastInputCurrency, name, coinsnap, type, redirect, metadata) => {

    const url = coinsnap
        ? `https://app.coinsnap.io/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData.coinsnapStoreId}/invoices/${invoiceId}`
        : `${Coinsnap_Bitcoin_Crowdfunding_sharedData.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData.btcpayStoreId}/invoices/${invoiceId}`;

    const headers = coinsnap
        ? {
            'x-api-key': Coinsnap_Bitcoin_Crowdfunding_sharedData.coinsnapApiKey,
            'Content-Type': 'application/json'

        }
        : {
            'Authorization': `token ${Coinsnap_Bitcoin_Crowdfunding_sharedData.btcpayApiKey}`,
            'Content-Type': 'application/json'
        };

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: headers
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        var responseData = await response.json();

        if (!coinsnap) {
            const url = `${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayStoreId}/invoices/${responseData.id}/payment-methods`;
            const response2 = await fetch(url, {
                method: 'GET',
                headers: headers
            });
            const responseData2 = await response2.json();
            const paymentLink = responseData2[0].paymentLink;
            console.log('Payment Link:', paymentLink);
            responseData.lightningInvoice = paymentLink?.replace('lightning:', '');
            responseData.onchainAddress = '';

            // Generate QR code image from lightning invoice
            const qrCodeImage = await generateQRCodeDataURL(paymentLink);
            responseData.qrCodes = {
                lightningQR: qrCodeImage || paymentLink
            };
        }

        if (responseData?.status === 'Settled') {
            return await createActualCrowdfundingInvoice(amount, message, lastInputCurrency, name, coinsnap, type, redirect, metadata);
        } else if (responseData?.status === 'New') {
            if (redirect) {
                window.location.href = responseData.checkoutLink;
            }
            return responseData;
        }

    } catch (error) {
        console.error('Error creating invoice:', error);
        return null;
    }
};

const createCrowdfundingInvoice = async (amount, amountFiat, lastInputCurrency,  message, name, type, redirect = true, metadata) => {
    existingInvoice = getCookie('coinsnap_invoice_crowdfunding');
    lastInputCurrency = 'SATS';
    if (existingInvoice) {
        invoiceJson = JSON.parse(existingInvoice);
        if (
            invoiceJson.id &&
            invoiceJson.checkoutLink &&
            invoiceJson.amount === amount &&
            invoiceJson.currency === lastInputCurrency &&
            invoiceJson.message === message &&
            invoiceJson.name === name
        ) {
            const cs = await checkCrowdfundingInvoiceStatus(
                invoiceJson.id,
                amount,
                message,
                lastInputCurrency,
                name,
                Coinsnap_Bitcoin_Crowdfunding_sharedData.provider === 'coinsnap',
                type,
                redirect,
                metadata
            );
            return cs;
        }
        else {
            return await createActualCrowdfundingInvoice(
                amount,
                message,
                lastInputCurrency,
                name,
                Coinsnap_Bitcoin_Crowdfunding_sharedData.provider === 'coinsnap',
                type,
                redirect,
                metadata
            );
        }
    } else {
        
        return await createActualCrowdfundingInvoice(
            amount,
            message,
            lastInputCurrency,
            name,
            Coinsnap_Bitcoin_Crowdfunding_sharedData.provider === 'coinsnap',
            type,
            redirect,
            metadata
        );
    }
};

const addCrowdfundingNumSeparators = (amount) => {
    var tmp = removeCrowdfundingThousandSeparator(amount);
    var val = Number(tmp).toLocaleString("en-GB");

    if (tmp === '') {
        return '';
    } else {
        return val;
    }

};

const getCrowdfundingThousandSeparator = () => {
    return (1000).toLocaleString("en-GB").replace(/\d/g, '')[0];
};

const removeCrowdfundingThousandSeparator = (amount) => {
    const sep = getCrowdfundingThousandSeparator();
    return amount; //amount?.replace(new RegExp(`\\${sep}`, 'g'), '');

};

const cleanCrowdfundingAmount = (amount) => {
    return parseFloat(removeCrowdfundingThousandSeparator(amount));

};

const NumericICrowdfundingnput = (inputFieldName) => {
    const inp = document.getElementById(inputFieldName);
    if (inp) {
        const sep = getCrowdfundingThousandSeparator() === "." ? "," : ".";
        var numericKeys = `0123456789${sep}`;

        inp.addEventListener('keypress', function (e) {
            var event = e || window.event;
            var target = event.target;

            if (event.charCode === 0) {
                return;
            }
            if (`${inp.value}`.includes(sep) && event.key === sep) {
                event.preventDefault();
                return;
            }
            if (-1 === numericKeys.indexOf(event.key)) {
                event.preventDefault();
                return;
            }
        });

        inp.addEventListener('blur', function (e) {
            var event = e || window.event;
            var target = event.target;
            var tmp = removeCrowdfundingThousandSeparator(target.value);
            var original = tmp;
            tmp = parseFloat(tmp);
            original = original.replace(tmp, "");
            var val = Number(tmp).toLocaleString("en-GB");
            if (tmp === '') {
                target.value = '';
            } else {
                target.value = `${val}${original}`;
            }
        });

        inp.addEventListener('focus', function (e) {
            var event = e || window.event;
            var target = event.target;
            var val = removeCrowdfundingThousandSeparator(target.value);
            target.value = val;
        });
        inp.blur();
    }
};

const limitCrowdfundingCursorMovement = (e, primaryCurrency) => {
    const field = e.target;
    const position = field.selectionStart;
    const satsOffset = primaryCurrency === 'sats' ? 5 : 4;
    const satsStart = field.value.length - satsOffset;

    if (field.value.includes(primaryCurrency)) {
        if (e.type === 'click' && position >= satsStart) {
            let value = field.value.replace(` ${primaryCurrency}`, '');
            field.setSelectionRange(value.length, value.length);
        }

        if (e.type === 'keydown' && (e.key === 'ArrowRight' || e.key === 'End') && position >= satsStart) {
            e.preventDefault();
            field.setSelectionRange(satsStart, satsStart);
        }
    }
};

const hideCrowdfundingElementById = (id, prefix = '', sufix = '') => {
    document.getElementById(`${prefix}${id}${sufix}`).style.display = 'none';
};
const hideCrowdfundingElementsById = (ids, prefix = '', sufix = '') => {
    ids.forEach(id => {
        hideCrowdfundingElementById(id, prefix, sufix);
    });
};
const showCrowdfundingElementById = (id, display, prefix = '', sufix = '') => {
    document.getElementById(`${prefix}${id}${sufix}`).style.display = display;
};
const showCrowdfundingElementsById = (ids, display, prefix = '', sufix = '') => {
    ids.forEach(id => {
        showCrowdfundingElementById(id, display, prefix, sufix);
    });
};