'use strict'

class WebauthnTFA {

// Decodes a Base64Url string
    _base64UrlDecode = (input) => {
        input = input
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const pad = input.length % 4;
        if (pad) {
            if (pad === 1) {
                throw new Error('InvalidLengthError: Input base64url string is the wrong length to determine padding');
            }
            input += new Array(5-pad).join('=');
        }

        return window.atob(input);
    };

    // Converts an array of bytes into a Base64Url string
    _arrayToBase64String = (a) => btoa(String.fromCharCode(...a));

    // Prepares the public key options object returned by the Webauthn Framework
    _preparePublicKeyOptions = publicKey => {
        //Convert challenge from Base64Url string to Uint8Array
        publicKey.challenge = Uint8Array.from(
            this._base64UrlDecode(publicKey.challenge),
            c => c.charCodeAt(0)
        );

        //Convert the user ID from Base64 string to Uint8Array
        if (publicKey.user !== undefined) {
            publicKey.user = {
                ...publicKey.user,
                id: Uint8Array.from(
                    window.atob(publicKey.user.id),
                    c => c.charCodeAt(0)
                ),
            };
        }

        //If excludeCredentials is defined, we convert all IDs to Uint8Array
        if (publicKey.excludeCredentials !== undefined) {
            publicKey.excludeCredentials = publicKey.excludeCredentials.map(
                data => {
                    return {
                        ...data,
                        id: Uint8Array.from(
                            this._base64UrlDecode(data.id),
                            c => c.charCodeAt(0)
                        ),
                    };
                }
            );
        }

        if (publicKey.allowCredentials !== undefined) {
            publicKey.allowCredentials = publicKey.allowCredentials.map(
                data => {
                    return {
                        ...data,
                        id: Uint8Array.from(
                            this._base64UrlDecode(data.id),
                            c => c.charCodeAt(0)
                        ),
                    };
                }
            );
        }

        return publicKey;
    };

// Prepares the public key credentials object returned by the authenticator
    _preparePublicKeyCredentials = data => {
        const publicKeyCredential = {
            id: data.id,
            type: data.type,
            rawId: this._arrayToBase64String(new Uint8Array(data.rawId)),
            response: {
                clientDataJSON: this._arrayToBase64String(
                    new Uint8Array(data.response.clientDataJSON)
                ),
            },
        };

        if (data.response.attestationObject !== undefined) {
            publicKeyCredential.response.attestationObject = this._arrayToBase64String(
                new Uint8Array(data.response.attestationObject)
            );
        }

        if (data.response.authenticatorData !== undefined) {
            publicKeyCredential.response.authenticatorData = this._arrayToBase64String(
                new Uint8Array(data.response.authenticatorData)
            );
        }

        if (data.response.signature !== undefined) {
            publicKeyCredential.response.signature = this._arrayToBase64String(
                new Uint8Array(data.response.signature)
            );
        }

        if (data.response.userHandle !== undefined) {
            publicKeyCredential.response.userHandle = this._arrayToBase64String(
                new Uint8Array(data.response.userHandle)
            );
        }

        return publicKeyCredential;
    };


    constructor()
    {
        const register_dom_ready = (fn) => {
            if (document.readyState !== 'loading') {
                fn();
            } else {
                document.addEventListener('DOMContentLoaded', fn);
            }
        }

        register_dom_ready(() => {
            this.registerForms();
        });
    }

    registerForms()
    {
        //Find all forms which have an data-webauthn-tfa-action attribute
        const forms = document.querySelectorAll('form[data-webauthn-tfa-action]');

        forms.forEach((form) => {
            console.debug('Found webauthn TFA form with action: ' + form.getAttribute('data-webauthn-tfa-action'), form);
            //Ensure that the form has webauthn data

            const dataString = form.getAttribute('data-webauthn-tfa-data')
            const action = form.getAttribute('data-webauthn-tfa-action');

            if (!dataString) {
                console.error('Form does not have webauthn data, can not continue!', form);
                return;
            }

            //Convert dataString to the needed dataObject
            const dataObject = JSON.parse(dataString);
            const options = this._preparePublicKeyOptions(dataObject);


            if(action === 'authenticate'){
                this.authenticate(form, {publicKey: options});
            }

            if(action === 'register'){
                //Register submit action, so we can do the registration on submit
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.register(form, {publicKey: options});
                });
            }



            //Catch submit event and do webauthn stuff


        });
    }

    /**
     * Submit the form with the given result data
     * @param form
     * @param data
     * @private
     */
    _submit(form, data)
    {
        const resultField = document.getElementById('_auth_code');
        resultField.value = JSON.stringify(data)
        form.submit();
    }

    authenticate(form, authData)
    {
        navigator.credentials.get(authData)
            .then((credential) => {
                //Convert our credential to a form which can be JSON encoded
                let data = this._preparePublicKeyCredentials(credential);

                this._submit(form, data)
            })
            .catch((error) => {
                console.error("WebAuthn Authentication error: ", error);
                alert("Error: " + error)
            });
    }

    register(form, authData)
    {
        navigator.credentials.create(authData)
            .then((credential) => {
                //Convert our credential to a form which can be JSON encoded
                let data = this._preparePublicKeyCredentials(credential);

                this._submit(form, data)
            })
            .catch((error) => {
                console.error("WebAuthn Registration error: ", error);
                alert("Error: " + error)
            });
    }
}

window.webauthnTFA = new WebauthnTFA();