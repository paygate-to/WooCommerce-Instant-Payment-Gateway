( function () {
    const registry = window.wc && window.wc.wcBlocksRegistry;
    const element = window.wp && window.wp.element;

    if ( ! registry || ! element || typeof registry.registerPaymentMethod !== 'function' ) {
        return;
    }

    const { registerPaymentMethod } = registry;
    const { createElement, useState, useEffect } = element;
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

    // Custom provider mode with more than one provider: the customer chooses one, and the
    // choice is sent as paygatedotto_selected_provider (WooCommerce copies it into $_POST).
    const buildProviderContent = ( paygatedottogateway ) => {
        const providers = paygatedottogateway.providers;
        const chosen = { current: providers[ 0 ].id };

        const ProviderSelector = ( props ) => {
            const { eventRegistration, emitResponse } = props;
            const { onPaymentSetup } = eventRegistration || {};
            const [ selected, setSelected ] = useState( chosen.current );

            useEffect( () => {
                chosen.current = selected;
            }, [ selected ] );

            useEffect( () => {
                if ( typeof onPaymentSetup !== 'function' ) {
                    return;
                }
                return onPaymentSetup( () => {
                    if ( ! chosen.current ) {
                        return {
                            type: emitResponse.responseTypes.ERROR,
                            message: 'Please choose how you want to pay.',
                        };
                    }
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                paygatedotto_selected_provider: chosen.current,
                            },
                        },
                    };
                } );
            }, [ onPaymentSetup, emitResponse ] );

            return createElement(
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
                    : null,
                createElement( 'p', null, 'Choose how you want to pay:' ),
                providers.map( ( provider ) =>
                    createElement(
                        'label',
                        {
                            key: provider.id,
                            className: 'paygatedottogateway-provider-option',
                            style: { display: 'flex', alignItems: 'center', gap: '8px', margin: '4px 0' },
                        },
                        createElement( 'input', {
                            type: 'radio',
                            name: 'paygatedotto_selected_provider_block',
                            value: provider.id,
                            checked: selected === provider.id,
                            onChange: () => setSelected( provider.id ),
                        } ),
                        createElement( 'span', null, provider.label )
                    )
                )
            );
        };

        return createElement( ProviderSelector, null );
    };

    paygatedottogateways.forEach( ( paygatedottogateway ) => {
        const content =
            Array.isArray( paygatedottogateway.providers ) && paygatedottogateway.providers.length > 1
                ? buildProviderContent( paygatedottogateway )
                : buildContent( paygatedottogateway );

        registerPaymentMethod( {
            name: paygatedottogateway.id,
            paymentMethodId: paygatedottogateway.id,
            label: paygatedottogateway.label,
            ariaLabel: paygatedottogateway.label,
            canMakePayment: () => true,
            content: content,
            edit: content,
            supports: {
                features: [ 'products' ],
            },
        } );
    } );
} )();
