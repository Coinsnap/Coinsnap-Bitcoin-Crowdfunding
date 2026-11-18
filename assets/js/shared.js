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



async function generateQRCodeCrowdfundingDataURL(text) {
    try {
        const response = await fetch(`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(text)}`);
        const blob = await response.blob();
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.readAsDataURL(blob);
        });
    } catch (error) {
        return null;
    }
}

/*
 * The server creates the invoice. The browser sends only the crowdfunding ID,
 * the amount in sats and the donor's form fields; it never sees an API key.
 */
const createCrowdfundingInvoice = async (crowdfundingId, sats, donor) => {
    try {
        const response = await fetch(Coinsnap_Bitcoin_Crowdfunding_sharedData.invoiceUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({}, donor || {}, {
                crowdfunding_id: crowdfundingId,
                amount: Math.round(sats)
            }))
        });
        const responseData = await response.json();
        if (!response.ok) {
            return { error: responseData?.message || 'Error' };
        }

        if (Coinsnap_Bitcoin_Crowdfunding_sharedData.provider === 'btcpay' && responseData.paymentLink) {
            const qrCodeImage = await generateQRCodeCrowdfundingDataURL(responseData.paymentLink);
            responseData.qrCodes = { lightningQR: qrCodeImage || responseData.paymentLink };
        }
        return responseData;
    } catch (error) {
        return null;
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