// Initialize API
const api = new InvestmentApi(document.querySelector('meta[name="app-url"]').content + '/api');
let currentStock = null;
const root = document.documentElement;

// Toggle senior mode: enlarge text and touch targets
const seniorToggle = document.getElementById('senior-toggle');
seniorToggle.addEventListener('change', (e) => {
    if (e.target.checked) {
        root.style.setProperty('--base-font', '20px');
        root.style.setProperty('--min-touch', '56px');
        announce('Senior mode enabled');
    } else {
        root.style.setProperty('--base-font', '18px');
        root.style.setProperty('--min-touch', '48px');
        announce('Senior mode disabled');
    }
});

// Wizard controls
let curStep = 1;
const totalSteps = 3;
const showStep = (n) => {
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    const el = document.querySelector('[data-step="' + n + '"]');
    if (el) el.classList.add('active');
    // back button visibility
    document.getElementById('btn-back').style.display = (n > 1) ? 'inline-flex' : 'none';
    // change button text
    document.getElementById('btn-next').textContent = (n === totalSteps) ? 'Confirm Investment' : 'Continue';
};

document.getElementById('btn-next').addEventListener('click', async () => {
    if (curStep < totalSteps) {
        curStep++;
        showStep(curStep);

        // Load stocks when entering step 2
        if (curStep === 2) {
            await loadStocksForWizard();
        }
    } else {
        // confirm
        await confirmInvestment();
    }
});

document.getElementById('btn-back').addEventListener('click', () => {
    if (curStep > 1) { curStep--; showStep(curStep) }
});

// chips quick set
document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', (ev) => {
    document.getElementById('amount').value = ev.target.dataset.value;
}));

// main tiles actions
document.querySelectorAll('.tile').forEach(t => t.addEventListener('click', () => {
    const act = t.dataset.action;
    if (act === 'put-in') {
        // open wizard at step 1
        document.getElementById('wizard-area').classList.remove('hidden');
        curStep = 1;
        showStep(curStep);
        t.setAttribute('aria-pressed', 'true');
        window.scrollTo({ top: document.getElementById('wizard-area').offsetTop - 20, behavior: 'smooth' });
    } else if (act === 'take-out') {
        // simple withdraw confirmation flow (placeholder)
        if (confirm('Are you sure you want to withdraw money? This feature is coming soon.')) {
            alert('Withdrawal requested. We will process it shortly.');
        }
    }
}));

// bottom nav
document.querySelectorAll('.nav-item').forEach(n => n.addEventListener('click', () => {
    if (n.id === 'logout-btn') {
        logout();
        return;
    }

    document.querySelectorAll('.nav-item').forEach(x => x.classList.remove('active'));
    n.classList.add('active');
    announce(n.textContent + ' tab');

    if (n.dataset.tab === 'help') {
        document.querySelector('[aria-label="Help"]').scrollIntoView({ behavior: 'smooth' });
    } else if (n.dataset.tab === 'activities') {
        document.querySelector('[aria-label="Activities"]').scrollIntoView({ behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}));

// help buttons
document.getElementById('call-support').addEventListener('click', () => {
    alert('Support: +1-800-INVEST\n\nThis is a demo. In production, this would initiate a phone call.');
});

document.getElementById('chat-support').addEventListener('click', () => {
    alert('Opening chat support...\n\nThis is a demo. In production, this would open a live chat window.');
});

// Load user data
async function loadUserData() {
    if (!api.token) {
        window.location.href = document.querySelector('meta[name="app-url"]').content;
        return;
    }

    const data = await api.getSummary();

    if (data.success) {
        document.getElementById('userBalance').textContent = '$' + parseFloat(data.data.balance).toFixed(2);
    } else {
        // console.error('Failed to load user data:', data.message);
    }
}

// Load stocks for wizard
async function loadStocksForWizard() {
    const data = await api.getStocks();

    if (!data.success) {
        // console.error('Failed to load stocks:', data.message);
        return;
    }

    const stockList = document.getElementById('stock-list-wizard');
    stockList.innerHTML = data.data.map(stock => `
    <div class="row" role="button" tabindex="0" data-stock='${JSON.stringify(stock)}'>
      <div style="flex:1">
        <div class="meta">${stock.symbol} - ${stock.name}</div>
        <div class="sub">$${stock.current_price} per share</div>
      </div>
      <div style="font-weight:700;color:${stock.change_percentage >= 0 ? 'green' : 'red'}">
        ${stock.change_percentage >= 0 ? '+' : ''}${stock.change_percentage}%
      </div>
    </div>
  `).join('');

    // Add click handlers
    document.querySelectorAll('#stock-list-wizard .row').forEach(r => {
        r.addEventListener('click', (ev) => {
            currentStock = JSON.parse(r.dataset.stock);
            // visually indicate selection
            document.querySelectorAll('#stock-list-wizard .row').forEach(x => x.style.outline = 'none');
            r.style.outline = '3px solid rgba(11,109,58,0.12)';
            announce(currentStock.symbol + ' selected');
        });
    });
}

// Confirm investment
async function confirmInvestment() {
    if (!currentStock) {
        alert('Please select a stock first');
        curStep = 2;
        showStep(curStep);
        return;
    }

    const amount = parseInt(document.getElementById('amount').value);
    const quantity = Math.floor(amount / currentStock.current_price);

    if (quantity < 1) {
        alert('Amount too small. Please increase the amount.');
        return;
    }

    // Update summary
    document.getElementById('confirm-summary').innerHTML =
        `You are investing <strong>$${amount}</strong> into <strong>${currentStock.symbol}</strong> (${quantity} shares at $${currentStock.current_price} each).`;

    const data = await api.buyStock(currentStock.symbol, quantity);

    if (data.success) {
        showSuccess(amount, currentStock.symbol, quantity);
        await loadUserData();
    } else {
        alert(data.message || 'Investment failed. Please try again.');
    }
}

function showSuccess(amount, symbol, quantity) {
    const area = document.getElementById('wizard-area');
    area.innerHTML = `
    <div class="card">
      <h2>Success! 🎉</h2>
      <p style="font-size:18px">You invested <strong>$${amount}</strong> into <strong>${symbol}</strong> (${quantity} shares).</p>
      <p style="color:var(--muted)">Your investment is protected & trackable.</p>
      <div style="margin-top:12px">
        <button class="btn btn-primary" id="done">Done</button>
      </div>
    </div>
  `;
    document.getElementById('done').addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        area.classList.add('hidden');
        area.innerHTML = `
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
        <div id="stock-list-wizard"></div>
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
    `;
        // Re-initialize wizard
        initWizard();
    });
}

function logout() {
    api.clearToken();
    window.location.href = document.querySelector('meta[name="app-url"]').content;
}

function announce(text) {
    const live = document.querySelector('#senior-ui');
    if (live) live.setAttribute('aria-busy', 'true');
    setTimeout(() => { if (live) live.setAttribute('aria-busy', 'false') }, 400);

}

function initWizard() {
    // Re-attach event listeners after wizard reset
    document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', (ev) => {
        document.getElementById('amount').value = ev.target.dataset.value;
    }));

    document.getElementById('btn-next').addEventListener('click', async () => {
        if (curStep < totalSteps) {
            curStep++;
            showStep(curStep);
            if (curStep === 2) {
                await loadStocksForWizard();
            }
        } else {
            await confirmInvestment();
        }
    });

    document.getElementById('btn-back').addEventListener('click', () => {
        if (curStep > 1) { curStep--; showStep(curStep) }
    });
}

// keyboard focus affordances for accessibility
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        const el = document.activeElement;
        if (el && el.click && el.tagName !== 'INPUT') el.click();
    }
});

// Initialize on load
window.onload = async () => {
    if (!api.token) {
        window.location.href = document.querySelector('meta[name="app-url"]').content;
        return;
    }
    await loadUserData();
};
