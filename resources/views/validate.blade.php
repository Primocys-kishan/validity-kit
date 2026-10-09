<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Validation</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .gradient-bg {
            background: linear-gradient(to bottom, #ffeb3b, #ffc107);
        }
    </style>
</head>

<body class="min-h-screen bg-gray-900 text-white">

<main class="min-h-screen flex items-center justify-center p-4"
      style="background: linear-gradient(135deg, #111827, #374151);">

    <div class="w-full max-w-4xl">

        <header class="text-center mb-8">
            <h1 class="text-3xl md:text-5xl font-semibold">
                Software Installation
            </h1>
            <p class="mt-4 text-gray-300">
                Enter your purchase information to activate your license.
            </p>
        </header>

        <section class="bg-white text-gray-900 rounded-xl shadow-xl p-6 md:p-10">

            <h2 class="text-xl font-semibold text-gray-700 mb-3">
                Update Purchase Information
            </h2>

            <p class="text-sm text-gray-600 mb-6">
                Enter your Envato/CodeCanyon username and purchase code.
            </p>

            <div id="message"
                 class="hidden mb-5 p-4 rounded-lg text-sm"
                 role="alert"></div>

            <form id="validateForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div>
                        <label for="username"
                               class="block text-sm font-medium mb-2">
                            Username <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            required
                            autocomplete="username"
                            placeholder="Enter your username"
                            class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-yellow-400 focus:outline-none"
                        >
                    </div>

                    <div>
                        <label for="purchase_code"
                               class="block text-sm font-medium mb-2">
                            Purchase Code <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            id="purchase_code"
                            name="purchase_code"
                            required
                            placeholder="Enter your purchase code"
                            class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-yellow-400 focus:outline-none"
                        >

                        <a
                            href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-block mt-2 text-sm text-blue-600 underline"
                        >
                            Where can I find my purchase code?
                        </a>
                    </div>

                </div>

                <div class="mt-8 text-center">
                    <button
                        type="submit"
                        id="submitBtn"
                        class="gradient-bg rounded-lg px-12 py-3 font-semibold text-gray-900 shadow hover:opacity-90 disabled:opacity-60"
                    >
                        Validate License
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                Please make sure your purchase details are correct.
            </div>
        </section>

        <footer class="text-center text-gray-400 text-sm mt-6">
            &copy; {{ date('Y') }} All Rights Reserved
        </footer>

    </div>
</main>

<script>
    const form = document.getElementById('validateForm');
    const submitBtn = document.getElementById('submitBtn');
    const messageBox = document.getElementById('message');

    function showMessage(message, success = false) {
        messageBox.textContent = message;
        messageBox.className = 'mb-5 p-4 rounded-lg text-sm ' +
            (success
                ? 'bg-green-100 text-green-800'
                : 'bg-red-100 text-red-800');

        messageBox.classList.remove('hidden');
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const username = document.getElementById('username').value.trim();
        const purchaseCode = document.getElementById('purchase_code').value.trim();

        if (!username || !purchaseCode) {
            showMessage('Username and purchase code are required.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Validating...';
        messageBox.classList.add('hidden');

        try {
            const response = await fetch(
                @json(url('/api/license/validate')),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        username: username,
                        purchase_code: purchaseCode
                    })
                }
            );

            const data = await response.json();

            if (!response.ok || data.success !== true) {
                showMessage(
                    data.message ||
                    data.error ||
                    'License validation failed. Please check your details.'
                );
                return;
            }

            showMessage(
                'License validation successful! Your license has been activated.',
                true
            );

            form.reset();

        } catch (error) {
            showMessage(
                'Unable to connect to the server. Please try again.'
            );
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Validate License';
        }
    });
</script>

</body>
</html>