// Payment popup target test: node tests/payment-target-test.js
// Which QR and wallet link the popup uses for an invoice from the server.
const { crowdfundingPaymentTarget } = require('../assets/js/shared.js');

let failed = 0;
const ok = (name, cond) => { console.log(`${cond ? 'PASS' : 'FAIL'} ${name}`); failed += cond ? 0 : 1; };

// Store without Lightning: Coinsnap returns an empty invoice and the on-chain BIP21 data.
const onchain = crowdfundingPaymentTarget({
    amount: 500, lightningInvoice: '', onchainAddress: 'bc1qexample',
    bip21: 'bitcoin:bc1qexample?amount=0.000005',
    qrCodes: { lightningQR: '', bip21QR: 'data:image/png;base64,BIP21' },
});
ok('no Lightning: bitcoin target', onchain.kind === 'bitcoin');
ok('no Lightning: BIP21 QR', onchain.qr === 'data:image/png;base64,BIP21');
ok('no Lightning: wallet link has the amount', onchain.link === 'bitcoin:bc1qexample?amount=0.000005');
ok('no Lightning: never an empty lightning: link', !onchain.link.startsWith('lightning:'));

// No bip21 from the server: built from the address and the amount in sats.
const built = crowdfundingPaymentTarget({ amount: 12345, lightningInvoice: '', onchainAddress: 'bc1qexample', qrCodes: { bitcoinQR: 'data:BTC' } });
ok('built BIP21 link with amount', built.link === 'bitcoin:bc1qexample?amount=0.00012345');
ok('bitcoinQR used when bip21QR is missing', built.qr === 'data:BTC');
ok('whole-BTC amount', crowdfundingPaymentTarget({ amount: 100000000, onchainAddress: 'bc1q' }).link === 'bitcoin:bc1q?amount=1');

// Lightning invoice present: Lightning QR and link.
const ln = crowdfundingPaymentTarget({ amount: 500, lightningInvoice: 'lnbc5u1example', onchainAddress: 'bc1q', bip21: 'bitcoin:bc1q?amount=0.000005', qrCodes: { lightningQR: 'data:LN', bip21QR: 'data:BIP21' } });
ok('Lightning: lightning target', ln.kind === 'lightning' && ln.qr === 'data:LN' && ln.link === 'lightning:lnbc5u1example');

// Nothing usable: no link, no QR (the popup then offers only the checkout page).
const none = crowdfundingPaymentTarget({ amount: 500, lightningInvoice: '', onchainAddress: '', qrCodes: {} });
ok('nothing usable: no link and no QR', none.link === '' && none.qr === '' && none.kind === '');

console.log(failed ? `${failed} failed` : 'all passed');
process.exit(failed ? 1 : 0);
