
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

const addErrorCrowdfundingField = (field) => {
    field.css('border', '1px solid red');
    removeCrowdfundingBorderOnFocus(field, field)
}

const removeCrowdfundingBorderOnFocus = (field1, field2) => {
    field1.on('focus', function () {
        field2.css('border', '');
    })

}

const handleCrowdfundingButtonClick = (buttonId, honeypotId, amountId, satoshiId, messageId, lastInputCurrency, name) => {
    event.preventDefault();

    const button = document.getElementById(buttonId);
    button.disabled = true;

    const honeypot = document.getElementById(honeypotId);
    if (honeypot && honeypot.value) {
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
        addErrorCrowdfundingField(amountField);
        if (satoshiField) {
            addErrorCrowdfundingField(satoshiField);
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
        createCrowdfundingInvoice(amount, message, lastInputCurrency, name, type);
    } else {
        button.disabled = false;
    }
};

const handleCrowdfundingButtonClickMulti = (buttonId, honeypotId, amountId, messageId, lastInputCurrency, name) => {
    const button = document.getElementById(buttonId)
    button.disabled = true;
    event.preventDefault();
    const honeypot = document.getElementById(honeypotId)
    if (honeypot && honeypot.value) {
        event.preventDefault();
        return
    }
    const amountField = document.getElementById(amountId)
    const fiatAmount = cleanCrowdfundingAmount(amountField.value)

    if (!fiatAmount) {
        button.disabled = false;
        addErrorCrowdfundingField(amountField)
        event.preventDefault();
        return
    }

    const messageField = document.getElementById(messageId)
    const message = messageField ? messageField.value : ""
    const currency = lastInputCurrency.toUpperCase()
    const amount = fiatAmount;
    if (amount) {
        createCrowdfundingInvoice(amount, message, currency, name, 'Multi Amount Donation');
    }
}

const updateCrowdfundingValueField = (amount, fieldName, operation, exchangeRates, currency, multi = false) => {
    const currencyRate = exchangeRates[currency?.toUpperCase()];
    const field = document.getElementById(fieldName);

    if (!field) {
        console.error(`Field with ID "${fieldName}" not found.`);
        return;
    }
    if (multi && !isNaN(amount) && currencyRate) {
        const value = operation == '*' ? amount * currencyRate : amount / currencyRate;
        const decimals = value > 1000 ? 4 : 8;
        const valueDecimal = value.toFixed(operation == '*' ? decimals : 0);
        const newVal = addCrowdfundingNumSeparators(valueDecimal)
        field.textContent = newVal;
        return
    }
    if (!isNaN(amount) && currencyRate) {
        const value = operation == '*' ? amount * currencyRate : amount / currencyRate;
        const valueDecimal = value.toFixed(operation == '*' ? 8 : 0);
        const newVal = addCrowdfundingNumSeparators(valueDecimal)
        field.value = newVal
    } else {
        field.value = '';
    }
};

async function fetchCrowdfundingCoinsnapExchangeRates() {
    const exchangeRates = {}
    try {
        const response = await fetch(`https://app.coinsnap.io/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData.coinsnapStoreId}/rates`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'x-api-key': Coinsnap_Bitcoin_Crowdfunding_sharedData.coinsnapApiKey
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const data = await response.json();
        data
            .filter(item => item.currencyPair.includes("SATS")) // Filter only SATS rates
            .forEach(item => {
                const currency = item.currencyPair.replace("SATS_", ""); // Remove "SATS_" prefix
                exchangeRates[currency] = parseFloat(item.rate); // Update exchangeRates
            });

        return exchangeRates;
    } catch (error) {
        console.error('Error fetching exchange rates:', error);
        return null;
    }
}

const createActualCrowdfundingInvoice = async (amount, message, lastInputCurrency, name, coinsnap, type, redirect, metadata) => {
    deleteCookie('coinsnap_invoice_');

    const requestData = {
        amount: amount,
        currency: lastInputCurrency,
        redirectAutomatically: true,
        metadata: {
            orderNumber: message,
            referralCode: 'D19833',
            type: type,
            name: name,
            ...metadata //TEST with voting
        }
    };

    if (type == 'Donation Crowdfunding') {
        requestData.redirectUrl = sharedData?.redirectUrl || window.location.href
    }
    if (window.location.href.includes("localhost")) {
        requestData.redirectUrl = "https://coinsnap.io";
    }

    if (coinsnap) {
        requestData.referralCode = 'D19833';
    }

    const url = coinsnap
        ? `https://app.coinsnap.io/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData?.coinsnapStoreId}/invoices`
        : `${sharedData?.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayStoreId}/invoices`;

    const headers = coinsnap
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

        setCookie('coinsnap_invoice_', JSON.stringify(invoiceCookieData), 15);

        if (!coinsnap) {
            const url = `${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayUrl}/api/v1/stores/${Coinsnap_Bitcoin_Crowdfunding_sharedData?.btcpayStoreId}/invoices/${responseData.id}/payment-methods`;
            const response2 = await fetch(url, {
                method: 'GET',
                headers: headers,
            });
            const responseData2 = await response2.json();
            const paymentLink = responseData2[0].paymentLink
            console.log('Payment Link:', paymentLink)
            responseData.lightningInvoice = paymentLink?.replace('lightning:', '')
            responseData.onchainAddress = ''

            // Generate QR code image from lightning invoice
            const qrCodeImage = await generateQRCodeDataURL(paymentLink);
            responseData.qrCodes = {
                lightningQR: qrCodeImage || paymentLink
            }
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
                headers: headers,
            });
            const responseData2 = await response2.json();
            const paymentLink = responseData2[0].paymentLink
            console.log('Payment Link:', paymentLink)
            responseData.lightningInvoice = paymentLink?.replace('lightning:', '')
            responseData.onchainAddress = ''

            // Generate QR code image from lightning invoice
            const qrCodeImage = await generateQRCodeDataURL(paymentLink);
            responseData.qrCodes = {
                lightningQR: qrCodeImage || paymentLink
            }
        }

        if (responseData?.status === 'Settled') {
            return await createActualCrowdfundingInvoice(amount, message, lastInputCurrency, name, coinsnap, type, redirect, metadata);
        } else if (responseData?.status === 'New') {
            if (redirect) {
                window.location.href = responseData.checkoutLink;
            }
            return responseData
        }

    } catch (error) {
        console.error('Error creating invoice:', error);
        return null;
    }
};

const createCrowdfundingInvoice = async (amount, message, lastInputCurrency, name, type, redirect = true, metadata) => {
    existingInvoice = getCookie('coinsnap_invoice_')
    if (existingInvoice) {
        invoiceJson = JSON.parse(existingInvoice)
        if (
            invoiceJson.id &&
            invoiceJson.checkoutLink &&
            invoiceJson.amount == amount &&
            invoiceJson.currency == lastInputCurrency &&
            invoiceJson.message == message &&
            invoiceJson.name == name
        ) {
            const cs = await checkCrowdfundingInvoiceStatus(
                invoiceJson.id,
                amount,
                message,
                lastInputCurrency,
                name,
                Coinsnap_Bitcoin_Crowdfunding_sharedData.provider == 'coinsnap',
                type,
                redirect,
                metadata
            )
            return cs
        }
        else {
            return await createActualCrowdfundingInvoice(
                amount,
                message,
                lastInputCurrency,
                name,
                Coinsnap_Bitcoin_Crowdfunding_sharedData.provider == 'coinsnap',
                type,
                redirect,
                metadata
            )
        }
    } else {
        return await createActualCrowdfundingInvoice(
            amount,
            message,
            lastInputCurrency,
            name,
            Coinsnap_Bitcoin_Crowdfunding_sharedData.provider == 'coinsnap',
            type,
            redirect,
            metadata
        )
    }
}

const addCrowdfundingNumSeparators = (amount) => {
    var tmp = removeCrowdfundingThousandSeparator(amount)
    var val = Number(tmp).toLocaleString("en-GB");

    if (tmp == '') {
        return '';
    } else {
        return val;
    }

}

const getCrowdfundingThousandSeparator = () => {
    return (1000).toLocaleString("en-GB").replace(/\d/g, '')[0];
}

const removeCrowdfundingThousandSeparator = (amount) => {
    const sep = getCrowdfundingThousandSeparator()
    return amount?.replace(new RegExp(`\\${sep}`, 'g'), '');

}

const cleanCrowdfundingAmount = (amount) => {
    return parseFloat(removeCrowdfundingThousandSeparator(amount))

}

const NumericICrowdfundingnput = (inputFieldName) => {
    const inp = document.getElementById(inputFieldName)
    if (inp) {
        const sep = getCrowdfundingThousandSeparator() == "." ? "," : ".";
        var numericKeys = `0123456789${sep}`;

        inp.addEventListener('keypress', function (e) {
            var event = e || window.event;
            var target = event.target;

            if (event.charCode == 0) {
                return;
            }
            if (`${inp.value}`.includes(sep) && event.key == sep) {
                event.preventDefault();
                return;
            }
            if (-1 == numericKeys.indexOf(event.key)) {
                event.preventDefault();
                return;
            }
        });

        inp.addEventListener('blur', function (e) {
            var event = e || window.event;
            var target = event.target;
            var tmp = removeCrowdfundingThousandSeparator(target.value)
            var original = tmp
            tmp = parseFloat(tmp)
            original = original.replace(tmp, "")
            var val = Number(tmp).toLocaleString("en-GB");
            if (tmp == '') {
                target.value = '';
            } else {
                target.value = `${val}${original}`
            }
        });

        inp.addEventListener('focus', function (e) {
            var event = e || window.event;
            var target = event.target;
            var val = removeCrowdfundingThousandSeparator(target.value)
            target.value = val;
        });
        inp.blur();
    }
}

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
}

const hideCrowdfundingElementById = (id, prefix = '', sufix = '') => {
    document.getElementById(`${prefix}${id}${sufix}`).style.display = 'none'
}
const hideCrowdfundingElementsById = (ids, prefix = '', sufix = '') => {
    ids.forEach(id => {
        hideCrowdfundingElementById(id, prefix, sufix)
    })
}
const showCrowdfundingElementById = (id, display, prefix = '', sufix = '') => {
    document.getElementById(`${prefix}${id}${sufix}`).style.display = display
}
const showCrowdfundingElementsById = (ids, display, prefix = '', sufix = '') => {
    ids.forEach(id => {
        showCrowdfundingElementById(id, display, prefix, sufix)
    })
}