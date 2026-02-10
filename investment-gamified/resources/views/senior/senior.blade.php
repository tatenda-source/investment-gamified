<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Senior Mode — Investment App</title>
  {{-- Allow JS to read the app URL for API calls --}}
  <meta name="app-url" content="{{ url('/') }}">
  <link rel="stylesheet" href="{{ asset('css/senior.css') }}">
</head>
<body>
  <main id="senior-ui" aria-live="polite">

    <header class="card" role="banner" aria-label="Senior mode header">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
          <h1>Your Money at a Glance</h1>
          <p>Protected & Trackable — simplified for confidence</p>
        </div>
        <div style="text-align:right">
          <div style="font-size:14px;margin-bottom:8px">Easy View Mode</div>
          <label style="display:inline-flex;align-items:center;gap:8px;background:#fff;padding:8px;border-radius:999px;border:1px solid #eee;">
            <input id="senior-toggle" type="checkbox" checked aria-label="Toggle Senior Mode"/>
            <span style="font-weight:700">ON</span>
          </label>
          <div style="margin-top:8px">
            <a href="{{ url('/toggle-ui') }}" style="font-size:14px;color:var(--accent);text-decoration:underline">Switch to Normal View</a>
          </div>
        </div>
      </div>

      <div style="margin-top:12px" class="balance card">
        <div>
          <div class="amount" id="userBalance">$0</div>
          <div class="tag">Protected & Trackable</div>
        </div>
        <div style="text-align:right">
          <div style="font-size:14px;color:var(--muted)">Account status</div>
          <div style="font-weight:700;margin-top:6px">Active</div>
        </div>
      </div>

      <div class="action-row" aria-hidden="false">
        <div class="tile in card" data-action="put-in" tabindex="0" role="button" aria-pressed="false">
          <div class="label">Put Money In</div>
          <div class="desc">Quickly add funds to an investment</div>
        </div>
        <div class="tile out card" data-action="take-out" tabindex="0" role="button" aria-pressed="false">
          <div class="label">Take Money Out</div>
          <div class="desc">Withdraw money back to your bank</div>
        </div>
      </div>

    </header>

    <!-- Wizard area -->
    <section id="wizard-area" class="card wizard hidden" aria-live="polite">
      <!-- Steps are hidden/shown by JS -->
      <div id="step-1" class="step active" data-step="1">
        <h2>Step 1 — Choose Amount</h2>
        <p>Tap a quick amount or type your own.</p>
        <div class="amount-input">
          <input id="amount" class="numeric" type="number" min="1" value="100" aria-label="Amount to invest" />
          <div class="chips" role="list">
            <button class="chip" data-value="100">100</button>
            <button class="chip" data-value="300">300</button>
            <button class="chip" data-value="500">500</button>
            <button class="chip" data-value="1000">1000</button>
          </div>
        </div>
      </div>

      <div id="step-2" class="step" data-step="2">
        <h2>Step 2 — Choose Stock</h2>
        <p>Pick a stock with a single tap.</p>
        <div id="stock-list-wizard">
          <!-- Stocks will be loaded here -->
        </div>
      </div>

      <div id="step-3" class="step" data-step="3">
        <h2>Step 3 — Confirm</h2>
        <div class="card" style="padding:14px">
          <div style="font-size:18px;font-weight:700">Summary</div>
          <p id="confirm-summary">You are investing <strong>$100</strong> into <strong>Stock</strong>.</p>
        </div>
        <p style="margin-top:12px">If everything looks correct, press Confirm. You can cancel anytime.</p>
      </div>

      <div class="fixed-action">
        <button class="btn btn-ghost" id="btn-back" style="display:none">Back</button>
        <button class="btn btn-primary" id="btn-next">Continue</button>
      </div>
    </section>

    <!-- Activities -->
    <section class="card" aria-label="Activities" style="margin-top:16px">
      <h2>Latest Activity</h2>
      <div id="activities-list">
        <p class="text-gray-500 text-sm">No recent activity</p>
      </div>
    </section>

    <!-- Help -->
    <section class="card" aria-label="Help" style="margin-top:16px">
      <h2>Help</h2>
      <p>If you need help, choose an option below.</p>
      <div class="help-grid">
        <button class="help-btn" id="call-support">Call Support</button>
        <button class="help-btn" id="chat-support">Chat With Us</button>
      </div>
      <div style="margin-top:12px">
        <details>
          <summary style="font-size:18px;font-weight:700;cursor:pointer">How do I invest?</summary>
          <p style="font-size:16px">Tap "Put Money In" on the main screen, choose an amount, select a stock, and confirm.</p>
        </details>
        <details style="margin-top:8px">
          <summary style="font-size:18px;font-weight:700;cursor:pointer">How do I withdraw funds?</summary>
          <p style="font-size:16px">Tap "Take Money Out" on the main screen, choose an amount and confirm withdrawal.</p>
        </details>
      </div>
    </section>

    <!-- Bottom navigation -->
    <nav class="bottom-nav" aria-label="Primary">
      <div class="nav-item active" data-tab="home" role="button" tabindex="0">Home</div>
      <div class="nav-item" data-tab="activities" role="button" tabindex="0">Activities</div>
      <div class="nav-item" data-tab="help" role="button" tabindex="0">Help</div>
      <div class="nav-item" id="logout-btn" role="button" tabindex="0">Logout</div>
    </nav>

  </main>

  <script src="{{ asset('js/services/InvestmentApi.js') }}"></script>
  <script src="{{ asset('js/senior.js') }}"></script>
</body>
</html>
