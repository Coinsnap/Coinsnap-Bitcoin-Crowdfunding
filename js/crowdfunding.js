// js/script.js
jQuery(document).ready(function ($) {
    var exchangeRates = {};
    var defaultCurrency = 'sats'
    var multiPrimaryCurrency = 'sats'
    var multiSecondaryCurrency = 'EUR'
    const crowdfundingDonation = document.getElementById('coinsnap-bitcoin-crowdfunding-amount-crowdfunding')
    if (crowdfundingDonation) {
        defaultCurrency = document.getElementById('coinsnap-bitcoin-crowdfunding-swap-crowdfunding').dataset.defaultCurrency
        multiPrimaryCurrency = defaultCurrency
        multiSecondaryCurrency = multiPrimaryCurrency == 'sats' ? "EUR" : multiPrimaryCurrency

        const crowdfundingDefaults = () => {
            for (let i = 1; i <= 3; i++) {
                const amountField = document.getElementById(`coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap${i}-primary`);
                updateSecondaryCurrency(
                    `coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap${i}-primary`,
                    `coinsnap-bitcoin-crowdfunding-pay-crowdfunding-snap${i}-secondary`,
                    amountField?.dataset?.defaultValue
                )
            }
        }

        fetchCrowdfundingCoinsnapExchangeRates().then(rates => {
            exchangeRates = rates
            if (crowdfundingDonation) {
                crowdfundingDefaults()
                addPopupListener('coinsnap-bitcoin-crowdfunding-', '-crowdfunding', 'Bitcoin Crowdfunding', exchangeRates)
            }
        });

        const updateSecondaryCurrency = (primaryId, secondaryId, originalAmount) => {
            const currency = multiSecondaryCurrency == 'sats' ? multiPrimaryCurrency : multiSecondaryCurrency
            const currencyRate = exchangeRates[currency];
            const primaryField = document.getElementById(primaryId)
            var amount = cleanCrowdfundingAmount(originalAmount)
            if (primaryId.includes("snap")) {
                primaryField.textContent = `${amount} ${multiPrimaryCurrency}`
            } else {
                amount = cleanCrowdfundingAmount(primaryField.value)
            }
            const converted = multiPrimaryCurrency == 'sats' ? (amount * currencyRate).toFixed(8) : (amount / currencyRate).toFixed(0)
            const withSeparators = addCrowdfundingNumSeparators(converted)
            document.getElementById(secondaryId).textContent = `≈ ${withSeparators} ${multiSecondaryCurrency}`
        }

        const handleAmountInput = () => {
            const field = document.getElementById(`coinsnap-bitcoin-crowdfunding-amount-crowdfunding`)
            const field2 = document.getElementById(`coinsnap-bitcoin-crowdfunding-satoshi-crowdfunding`)
            let value = field.value.replace(/[^\d.,]/g, '');
            const decimalSeparator = getCrowdfundingThousandSeparator() == "." ? "," : ".";
            if (value[0] == '0' && value[1] != decimalSeparator && value.length > 1) {
                value = value.substring(1);
            }
            if (value.trim() !== '') {
                field.value = value + ` ${multiPrimaryCurrency}`;
                updateSecondaryCurrency(`coinsnap-bitcoin-crowdfunding-amount-crowdfunding`, `coinsnap-bitcoin-crowdfunding-satoshi-crowdfunding`, value)
            } else {
                field.value = 0;
                field2.textContent = 0 + " " + multiSecondaryCurrency
            }
        }

        const swapSnapCurrency = (primaryId, secondaryId) => {
            const currency = multiSecondaryCurrency == 'sats' ? multiPrimaryCurrency : multiSecondaryCurrency
            const currencyRate = exchangeRates[currency];
            const primaryField = document.getElementById(primaryId)
            const primaryAmount = cleanCrowdfundingAmount(primaryField.textContent)
            const secondaryField = document.getElementById(secondaryId)
            const convertedPrimary = (primaryAmount / currencyRate).toFixed(0)
            primaryField.textContent = `${convertedPrimary} ${multiPrimaryCurrency}`
            secondaryField.textContent = `≈ ${primaryAmount} ${multiSecondaryCurrency}`

        }

        // Update secondary values
        $('#coinsnap-bitcoin-crowdfunding-amount-crowdfunding').on('input', () => { handleAmountInput(false) });

        // Handle thousands separators
        NumericICrowdfundingnput('coinsnap-bitcoin-crowdfunding-amount-crowdfunding')

        // Limit cursor movement
        $('#coinsnap-bitcoin-crowdfunding-amount-crowdfunding').on('click keydown', (e) => { limitCrowdfundingCursorMovement(e, multiPrimaryCurrency); });

        // Update snap buttons
        const snapIds = ['snap1', 'snap2', 'snap3'];
        snapIds.forEach(snapId => {
            const payButtonId = `coinsnap-bitcoin-crowdfunding-pay-crowdfunding-${snapId}`;
            const primaryId = `coinsnap-bitcoin-crowdfunding-pay-crowdfunding-${snapId}-primary`;
            $(`#${payButtonId}`).on('click', () => {
                const amountField = $(`#coinsnap-bitcoin-crowdfunding-amount-crowdfunding`);
                const amount = cleanCrowdfundingAmount(document.getElementById(primaryId).textContent)
                amountField.val(`${amount} ${multiPrimaryCurrency}`);
                amountField.trigger('input');
            });
        });

        const handleMultiChangeCurrency = () => {
            const newCurrency = $(`#coinsnap-bitcoin-crowdfunding-swap-crowdfunding`).val();
            multiPrimaryCurrency = newCurrency;
            multiSecondaryCurrency = (newCurrency === 'sats') ? 'EUR' : 'sats';

            const amountField = $(`#coinsnap-bitcoin-crowdfunding-amount-crowdfunding`);
            const amountValue = cleanCrowdfundingAmount(amountField.val()) || 0;
            amountField.val(`${amountValue} ${multiPrimaryCurrency}`);

            updateSecondaryCurrency(`coinsnap-bitcoin-crowdfunding-amount-crowdfunding`, `coinsnap-bitcoin-crowdfunding-satoshi-crowdfunding`);
            const snaps = ['snap1', 'snap2', 'snap3'];

            snaps.forEach(snap => {
                const primaryId = `coinsnap-bitcoin-crowdfunding-pay-crowdfunding-${snap}-primary`;
                const secondaryId = `coinsnap-bitcoin-crowdfunding-pay-crowdfunding-${snap}-secondary`;
                if (newCurrency !== 'sats') {
                    const amountField = document.getElementById(primaryId);
                    updateSecondaryCurrency(primaryId, secondaryId, amountField?.dataset?.defaultValue);
                } else {
                    swapSnapCurrency(primaryId, secondaryId);
                }
            });

        }

        // Handle currency change
        $('#coinsnap-bitcoin-crowdfunding-swap-crowdfunding').on('change', () => { handleMultiChangeCurrency(false); });
    }

});