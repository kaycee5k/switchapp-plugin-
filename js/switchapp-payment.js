document.addEventListener('DOMContentLoaded', function () {
    const ticketCategory = document.getElementById('ticket-category');
    const ticketAmountField = document.getElementById('ticket-amount');

    if (ticketCategory && ticketAmountField) {
        ticketCategory.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            ticketAmountField.value = price ? parseFloat(price) : '';
        });
    }

    const payNowButton = document.getElementById('pay-now-button');
    if (payNowButton) {
        payNowButton.addEventListener('click', function () {
            const fullName = `${document.getElementById('switchapp-first-name').value} ${document.getElementById('switchapp-last-name').value}`;
            const email = document.getElementById('switchapp-email').value;
            const phone = document.getElementById('switchapp-phone').value;
            const organization = document.getElementById('switchapp-organization').value;
            const amount = parseFloat(ticketAmountField.value);
            const ticketType = ticketCategory.value;

            if (isNaN(amount) || amount <= 0) {
                alert("Invalid amount. Please select a valid ticket category.");
                return;
            }

            if (!switchappConfig || !switchappConfig.publicKey) {
                alert("Payment configuration error. Please contact support.");
                return;
            }

            const switchappClient = new SwitchAppCheckout({ publicApiKey: switchappConfig.publicKey });

            const paymentDetails = {
                country: "NG",
                currency: "NGN",
                amount: amount,
                customer: { full_name: fullName, email, phone },
                metadata: { organization, ticket_type: ticketType },
                title: "Event Ticket Purchase",
                description: `Ticket purchase for ${ticketType}`,
                onClose: () => alert("Payment was canceled or closed."),
                onSuccess: () => {
                    // Only send data after successful payment
                    jQuery.ajax({
                        url: switchappConfig.ajaxUrl,
                        method: "POST",
                        data: {
                            action: "save_payment_details",
                            security: switchappConfig.nonce,
                            first_name: document.getElementById('switchapp-first-name').value,
                            last_name: document.getElementById('switchapp-last-name').value,
                            email: email,
                            phone: phone,
                            organization: organization,
                            amount: amount,
                            ticket_type: ticketType,
                        },
                        success: function(response) {
                            if (response.success) {
                                alert("Payment details saved successfully!");
                            } else {
                                console.error("Failed to save payment details:", response.data);
                            }
                        },
                        error: function(err) {
                            console.error("AJAX error:", err);
                        }
                    });
                },
            };

            switchappClient.showCheckoutModal(paymentDetails)
                .then(() => console.log("Payment initialized successfully"))
                .catch(err => console.error("Failed to initialize payment", err));
        });
    }
});
