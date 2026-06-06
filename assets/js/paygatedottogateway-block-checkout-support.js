( function () {
    const registry = window.wc && window.wc.wcBlocksRegistry;
    const element = window.wp && window.wp.element;

    if ( ! registry || ! element || typeof registry.registerPaymentMethod !== 'function' ) {
        return;
    }

    const { registerPaymentMethod } = registry;
    const { createElement } = element;
    const paygatedottogateways = window.paygatedottogatewayData || [];

    const buildContent = ( paygatedottogateway ) =>
        createElement(
            'div',
            { className: 'paygatedottogateway-method-wrapper' },
            createElement(
                'div',
                { className: 'paygatedottogateway-method-label' },
                '' + ( paygatedottogateway.description || '' )
            ),
            paygatedottogateway.icon_url
                ? createElement( 'img', {
                      src: paygatedottogateway.icon_url,
                      alt: paygatedottogateway.label,
                      className: 'paygatedottogateway-method-icon',
                  } )
                : null
        );

    paygatedottogateways.forEach( ( paygatedottogateway ) => {
        registerPaymentMethod( {
            name: paygatedottogateway.id,
            paymentMethodId: paygatedottogateway.id,
            label: paygatedottogateway.label,
            ariaLabel: paygatedottogateway.label,
            canMakePayment: () => true,
            content: buildContent( paygatedottogateway ),
            edit: buildContent( paygatedottogateway ),
            supports: {
                features: [ 'products' ],
            },
        } );
    } );
} )();
