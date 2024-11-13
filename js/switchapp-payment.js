document.addEventListener('DOMContentLoaded', function () {
    const ticketCategory = document.getElementById('ticket-category');
    const ticketQuantity = document.getElementById('ticket-quantity');
    const ticketAmountField = document.getElementById('ticket-amount');
    const quantityIncreaseButton = document.getElementById('quantity-increase');
    const quantityDecreaseButton = document.getElementById('quantity-decrease');
    const remainingTicketsDisplay = document.createElement('div'); // Element to show remaining tickets
    remainingTicketsDisplay.style.color = 'red';
    ticketCategory.parentNode.insertBefore(remainingTicketsDisplay, ticketCategory.nextSibling);

    // Update total amount based on ticket price and quantity
    function updateTotalAmount() {
        const selectedOption = ticketCategory.options[ticketCategory.selectedIndex];
        const price = selectedOption ? parseFloat(selectedOption.getAttribute('data-price')) : 0;
        const quantity = parseInt(ticketQuantity.value) || 1;
        ticketAmountField.value = price * quantity;

        // Check remaining tickets for the selected ticket type
        fetchRemainingTickets(ticketCategory.value);
    }

    // Event listeners for quantity controls
    quantityIncreaseButton.addEventListener('click', function () {
        let quantity = parseInt(ticketQuantity.value);
        ticketQuantity.value = quantity + 1;
        updateTotalAmount();
    });

    quantityDecreaseButton.addEventListener('click', function () {
        let quantity = parseInt(ticketQuantity.value);
        if (quantity > 1) {  // Ensure quantity doesn’t go below 1
            ticketQuantity.value = quantity - 1;
            updateTotalAmount();
        }
    });

    // Fetch remaining tickets for the selected type
    function fetchRemainingTickets(ticketType) {
        jQuery.ajax({
            url: switchappConfig.ajaxUrl,
            method: "POST",
            data: {
                action: "get_remaining_tickets",
                security: switchappConfig.nonce,
                ticket_type: ticketType,
            },
            success: function(response) {
                if (response.success && response.data.remaining_tickets !== null) {
                    remainingTicketsDisplay.innerHTML = `Only ${response.data.remaining_tickets} tickets left for ${ticketType}!`;
                } else {
                    remainingTicketsDisplay.innerHTML = ''; // Hide if more than 10 tickets remain
                }
            },
            error: function(err) {
                console.error("Error fetching remaining tickets:", err);
            }
        });
    }

    // Initialize total amount and remaining tickets on page load
    if (ticketCategory && ticketQuantity && ticketAmountField) {
        ticketCategory.addEventListener('change', updateTotalAmount);
        updateTotalAmount(); // Initial calculation
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
            const quantity = parseInt(ticketQuantity.value);

            // Validate inputs
            if (isNaN(amount) || amount <= 0) {
                alert("Invalid amount. Please select a valid ticket category and quantity.");
                return;
            }
            if (!switchappConfig || !switchappConfig.publicKey) {
                alert("Payment configuration error. Please contact support.");
                return;
            }

            // Initialize payment
            const switchappClient = new SwitchAppCheckout({ publicApiKey: switchappConfig.publicKey });
            const paymentDetails = {
                country: "NG",
                currency: "NGN",
                amount: amount,
                customer: { full_name: fullName, email, phone },
                metadata: { organization, ticket_type: ticketType, quantity },
                title: "Event Ticket Purchase",
                description: `Ticket purchase for ${ticketType} - ${quantity} tickets`,
                onClose: () => alert("Payment was canceled or closed."),
                onSuccess: () => {
                    // AJAX request to save payment details after successful payment
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
                            quantity: quantity,
                        },
                        success: function(response) {
                            if (response.success) {
                                alert("Payment details saved successfully!");
                            } else {
                                alert(response.data || "Failed to save payment details.");
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
                .catch(err => {
                    console.error("Failed to initialize payment", err);
                    alert("Failed to initialize payment. Check the console for details.");
                });
        });
    }
});
