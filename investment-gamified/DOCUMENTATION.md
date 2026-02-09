# Investment Gamified - Complete Documentation

## Table of Contents
1. [Project Overview](#project-overview)
2. [Architecture](#architecture)
3. [Technology Stack](#technology-stack)
4. [Project Structure](#project-structure)
5. [Database Schema](#database-schema)
6. [Models and Relationships](#models-and-relationships)
7. [API Endpoints](#api-endpoints)
8. [Services](#services)
9. [Controllers](#controllers)
10. [Authentication](#authentication)
11. [Gamification System](#gamification-system)
12. [External API Integration](#external-api-integration)
13. [Console Commands](#console-commands)
14. [Setup and Installation](#setup-and-installation)
15. [Configuration](#configuration)
16. [Development Workflow](#development-workflow)
17. [Best Practices](#best-practices)
18. [Future Enhancements](#future-enhancements)

---

## Project Overview

**Investment Gamified** is a Laravel-based web application that gamifies the stock market investment experience. The application allows users to:

- **Buy and Sell Stocks**: Simulate real-world trading with a virtual balance
- **Track Performance**: Monitor portfolio value and transaction history
- **Earn Achievements**: Unlock gamification rewards based on investment milestones
- **Climb Leaderboards**: Compete with other users
- **Experience Levels**: Progress through levels by earning experience points
- **Dual UI Modes**: Switch between normal and senior-friendly interfaces

The application integrates with real-time stock market APIs (Alpha Vantage and Financial Modeling Prep) to provide current stock quotes and historical data.

---

## Architecture

The application follows a **modular, service-oriented architecture** designed for maintainability and scalability:

```
Controller Layer
    ↓
Service Layer (Business Logic)
    ↓
Model Layer (Data Access)
    ↓
Database
```

### Design Principles

- **Thin Controllers**: Controllers delegate business logic to services
- **Service-Oriented**: Complex operations are encapsulated in service classes
- **Dependency Injection**: Services are injected into controllers via constructor injection
- **Database Transactions**: Critical operations use transactions to ensure data consistency

---

## Technology Stack

### Backend
- **Framework**: Laravel 12.x
- **PHP Version**: 8.2+
- **Database**: SQLite (configurable to MySQL, PostgreSQL)
- **API Authentication**: Laravel Sanctum

### Frontend
- **Build Tool**: Vite
- **CSS Framework**: Tailwind CSS 4.0
- **HTTP Client**: Axios
- **Template Engine**: Laravel Blade

### Development Tools
- **Testing**: PHPUnit 11.x
- **Code Quality**: Laravel Pint
- **Monitoring**: Laravel Pail
- **Debugging**: Laravel Tinker

### External APIs
- **Stock Data**: Alpha Vantage API
- **Additional Market Data**: Financial Modeling Prep (FMP) API

---

## Project Structure

```
investment-gamified/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── GameMarketTick.php          # Simulate market ticks
│   │       ├── ImportStockHistory.php      # Import historical data
│   │       ├── SeedStocksFromFmp.php       # Seed stocks from FMP API
│   │       └── UpdateStockPrices.php       # Update current stock prices
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Controller.php              # Base controller
│   │       ├── UiModeController.php        # UI mode switching
│   │       └── Api/
│   │           ├── AchievementController.php
│   │           ├── AuthController.php
│   │           ├── ExternalStockController.php
│   │           ├── PortfolioController.php
│   │           └── StockController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Stock.php
│   │   ├── Portfolio.php
│   │   ├── Transaction.php
│   │   ├── Achievement.php
│   │   └── StockHistory.php
│   ├── Services/
│   │   ├── PortfolioService.php            # Portfolio operations
│   │   ├── StockApiService.php             # Alpha Vantage API wrapper
│   │   └── FinancialModelingPrepService.php # FMP API wrapper
│   └── Providers/
│       └── AppServiceProvider.php
├── bootstrap/
│   ├── app.php
│   ├── providers.php
│   └── cache/
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── database.php
│   ├── services.php                        # Third-party API credentials
│   └── ... (other config files)
├── database/
│   ├── factories/
│   │   └── UserFactory.php
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2025_11_24_120318_create_stocks_table.php
│   │   ├── 2025_11_24_120318_create_portfolios_table.php
│   │   ├── 2025_11_24_120318_create_transactions_table.php
│   │   ├── 2025_11_24_120318_create_achievements_table.php
│   │   ├── 2025_11_24_120318_create_achievement_user_table.php
│   │   └── ... (others)
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── SimpleStockSeeder.php
│       ├── StockSeeder.php
│       └── FmpStocksSeeder.php
├── public/
│   ├── index.php                           # Application entry point
│   ├── css/
│   │   └── senior.css                      # Senior mode styling
│   └── js/
│       ├── normal.js
│       ├── senior.js
│       └── services/
│           └── InvestmentApi.js
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   └── bootstrap.js
│   └── views/
│       ├── normal/
│       │   └── ... (normal UI templates)
│       └── senior/
│           └── ... (senior UI templates)
├── routes/
│   ├── api.php                             # API routes
│   ├── web.php                             # Web routes
│   └── console.php
├── storage/
│   ├── app/
│   ├── framework/
│   └── logs/
├── tests/
│   ├── Feature/
│   │   ├── AuthRoutesTest.php
│   │   ├── ExternalStockRoutesTest.php
│   │   └── WelcomePageTest.php
│   └── Unit/
│       └── ExampleTest.php
├── composer.json
├── package.json
├── vite.config.js
├── phpunit.xml
└── README.md
```

---

## Database Schema

### Users Table
Stores user account information and gamification progress.

```
users
├── id (PK)
├── name (string)
├── email (string, unique)
├── password (hashed)
├── balance (decimal: current virtual balance)
├── level (integer: gamification level)
├── experience_points (integer: XP toward next level)
├── email_verified_at (timestamp)
├── remember_token
├── created_at
└── updated_at
```

**Starting Balance**: $10,000.00

### Stocks Table
Stores stock information sourced from APIs.

```
stocks
├── id (PK)
├── symbol (string, unique: e.g., "AAPL")
├── name (string: company name)
├── description (text: general description)
├── kid_friendly_description (text: simplified description)
├── fun_fact (text: interesting fact about company)
├── category (string: Tech, Food, Entertainment, etc.)
├── current_price (decimal: current quote)
├── change_percentage (decimal: % change)
├── logo_url (string: company logo URL)
├── created_at
└── updated_at
```

### Portfolios Table
Tracks stocks owned by users.

```
portfolios
├── id (PK)
├── user_id (FK → users)
├── stock_id (FK → stocks)
├── quantity (integer: shares owned)
├── average_price (decimal: average purchase price)
├── created_at
├── updated_at
└── UNIQUE(user_id, stock_id) ← One entry per user per stock
```

### Transactions Table
Records all buy/sell transactions.

```
transactions
├── id (PK)
├── user_id (FK → users)
├── stock_id (FK → stocks)
├── type (enum: 'buy' or 'sell')
├── quantity (integer: shares transacted)
├── price (decimal: price per share)
├── total_amount (decimal: quantity × price)
├── created_at
├── updated_at
└── INDEX(user_id, created_at)
```

### Achievements Table
Defines available achievements/badges.

```
achievements
├── id (PK)
├── name (string: achievement name)
├── description (text)
├── icon (string: icon URL/identifier)
├── xp_reward (integer: experience points granted)
├── requirement_type (string: type of condition)
├── requirement_value (any: condition value)
├── created_at
└── updated_at
```

### Achievement User Table (Pivot)
Links users to achieved achievements.

```
achievement_user
├── id (PK)
├── achievement_id (FK → achievements)
├── user_id (FK → users)
├── unlocked_at (timestamp: when achieved)
├── created_at
└── updated_at
```

### Stock History Table
Stores historical price data.

```
stock_history
├── id (PK)
├── stock_id (FK → stocks)
├── date (date)
├── open (decimal)
├── high (decimal)
├── low (decimal)
├── close (decimal)
├── volume (integer)
├── created_at
└── updated_at
```

---

## Models and Relationships

### User Model
**Namespace**: `App\Models\User`

**Attributes**:
- `name`, `email`, `password`, `balance`, `level`, `experience_points`

**Relationships**:
```php
// One-to-many
portfolios() → returns hasMany(Portfolio::class)
transactions() → returns hasMany(Transaction::class)

// Many-to-many
achievements() → returns belongsToMany(Achievement::class)
    .withTimestamps()
    .withPivot('unlocked_at')
```

**Key Methods**:
- Standard authentication methods (inherited from `Authenticatable`)
- Token generation via `createToken()` (from `HasApiTokens`)

---

### Stock Model
**Namespace**: `App\Models\Stock`

**Attributes**:
- `symbol`, `name`, `description`, `kid_friendly_description`, `fun_fact`, `category`, `current_price`, `change_percentage`, `logo_url`

**Casts**:
- `current_price` → `decimal:2`
- `change_percentage` → `decimal:2`

**Relationships**:
```php
portfolios() → returns hasMany(Portfolio::class)
transactions() → returns hasMany(Transaction::class)
history() → returns hasMany(StockHistory::class)
```

---

### Portfolio Model
**Namespace**: `App\Models\Portfolio`

**Attributes**:
- `user_id`, `stock_id`, `quantity`, `average_price`

**Casts**:
- `average_price` → `decimal:2`

**Relationships**:
```php
user() → returns belongsTo(User::class)
stock() → returns belongsTo(Stock::class)
```

**Purpose**: Represents current stock holdings; quantity of 0 indicates stock was sold.

---

### Transaction Model
**Namespace**: `App\Models\Transaction`

**Attributes**:
- `user_id`, `stock_id`, `type`, `quantity`, `price`, `total_amount`

**Casts**:
- `price` → `decimal:2`
- `total_amount` → `decimal:2`

**Relationships**:
```php
user() → returns belongsTo(User::class)
stock() → returns belongsTo(Stock::class)
```

**Purpose**: Immutable record of all trading activity; used for auditing and portfolio reconstruction.

---

### Achievement Model
**Namespace**: `App\Models\Achievement`

**Attributes**:
- `name`, `description`, `icon`, `xp_reward`, `requirement_type`, `requirement_value`

**Relationships**:
```php
users() → returns belongsToMany(User::class)
    .withTimestamps()
    .withPivot('unlocked_at')
```

---

### StockHistory Model
**Namespace**: `App\Models\StockHistory`

**Attributes**:
- `stock_id`, `date`, `open`, `high`, `low`, `close`, `volume`

**Relationships**:
```php
stock() → returns belongsTo(Stock::class)
```

---

## API Endpoints

### Authentication Endpoints

#### Register
```
POST /api/auth/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}

Response (201):
{
    "success": true,
    "user": { ... },
    "token": "token_string"
}
```

**Initial State**: New users start with $10,000 balance, level 1, 0 XP.

---

#### Login
```
POST /api/auth/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "password123"
}

Response (200):
{
    "success": true,
    "user": { ... },
    "token": "token_string"
}
```

---

#### Logout
```
POST /api/auth/logout
Authorization: Bearer {token}

Response (200):
{
    "success": true,
    "message": "Logged out successfully"
}
```

**Authentication Required**: Yes (Sanctum)

---

#### Get Authenticated User
```
GET /api/auth/user
Authorization: Bearer {token}

Response (200):
{
    "success": true,
    "user": { ... }
}
```

**Authentication Required**: Yes (Sanctum)

---

### Public Stock Endpoints

#### List All Stocks
```
GET /api/stocks

Response (200):
[
    {
        "id": 1,
        "symbol": "AAPL",
        "name": "Apple Inc.",
        "current_price": "150.25",
        "change_percentage": "2.5",
        ...
    },
    ...
]
```

**Authentication Required**: No

---

#### Get Stock Details
```
GET /api/stocks/{symbol}

Response (200):
{
    "id": 1,
    "symbol": "AAPL",
    "name": "Apple Inc.",
    "description": "...",
    "kid_friendly_description": "...",
    "fun_fact": "...",
    "current_price": "150.25",
    ...
}
```

**Authentication Required**: No

---

#### Get Stock Price History
```
GET /api/stocks/{symbol}/history

Response (200):
[
    {
        "id": 1,
        "date": "2025-02-09",
        "open": "145.50",
        "high": "152.00",
        "low": "145.00",
        "close": "150.25",
        "volume": 1000000
    },
    ...
]
```

**Authentication Required**: No

---

### Portfolio Endpoints (Protected)

#### Get User Portfolio
```
GET /api/portfolio
Authorization: Bearer {token}

Response (200):
[
    {
        "id": 1,
        "user_id": 1,
        "stock_id": 5,
        "quantity": 10,
        "average_price": "140.00",
        "stock": { ... },
        "created_at": "2025-02-01T10:30:00Z",
        "updated_at": "2025-02-09T15:45:00Z"
    },
    ...
]
```

**Authentication Required**: Yes (Sanctum)

---

#### Get Portfolio Summary
```
GET /api/portfolio/summary
Authorization: Bearer {token}

Response (200):
{
    "total_invested": "5000.00",
    "current_value": "5250.00",
    "gain_loss": "250.00",
    "gain_loss_percentage": "5.00",
    "cash_balance": "5000.00",
    "holdings_count": 3
}
```

**Authentication Required**: Yes (Sanctum)

---

#### Buy Stock
```
POST /api/portfolio/buy
Authorization: Bearer {token}
Content-Type: application/json

{
    "symbol": "AAPL",
    "quantity": 5
}

Response (200):
{
    "success": true,
    "message": "Stock purchased successfully",
    "data": {
        "xp_earned": 10
    }
}
```

**Authentication Required**: Yes (Sanctum)

**Validation**:
- Stock must exist
- User must have sufficient balance
- Quantity must be positive

**Side Effects**:
- Deducts balance from user
- Creates/updates portfolio entry
- Calculates new average price
- Records transaction
- Grants 10 XP
- May level up user

---

#### Sell Stock
```
POST /api/portfolio/sell
Authorization: Bearer {token}
Content-Type: application/json

{
    "symbol": "AAPL",
    "quantity": 2
}

Response (200):
{
    "success": true,
    "message": "Stock sold successfully",
    "data": {
        "proceeds": "300.50",
        "xp_earned": 10
    }
}
```

**Authentication Required**: Yes (Sanctum)

**Validation**:
- Stock must exist
- User must own sufficient shares
- Quantity must be positive

**Side Effects**:
- Credits balance to user
- Updates portfolio quantity
- Records transaction
- Grants 10 XP

---

### Gamification Endpoints (Protected)

#### List User Achievements
```
GET /api/achievements
Authorization: Bearer {token}

Response (200):
[
    {
        "id": 1,
        "name": "First Trade",
        "description": "Make your first stock purchase",
        "icon": "trophy-1",
        "xp_reward": 50,
        "pivot": {
            "user_id": 1,
            "achievement_id": 1,
            "unlocked_at": "2025-02-01T10:30:00Z"
        }
    },
    ...
]
```

**Authentication Required**: Yes (Sanctum)

---

#### Get Leaderboard
```
GET /api/leaderboard
Authorization: Bearer {token}

Response (200):
[
    {
        "rank": 1,
        "user_id": 5,
        "name": "Top Trader",
        "level": 15,
        "experience_points": 500,
        "achievements_count": 12,
        "portfolio_value": "25000.00"
    },
    {
        "rank": 2,
        "user_id": 3,
        "name": "Investor Pro",
        "level": 12,
        "experience_points": 750,
        "achievements_count": 10,
        "portfolio_value": "18500.00"
    },
    ...
]
```

**Authentication Required**: Yes (Sanctum)

**Sorted By**: Level (descending), then experience points (descending)

---

### External Stock API Endpoints (Protected with Rate Limiting)

These endpoints proxy requests to external stock APIs with 60 requests per minute rate limit.

#### Get Real-Time Quote
```
GET /api/external/stocks/quote/{symbol}
Authorization: Bearer {token}

Response (200):
{
    "symbol": "AAPL",
    "price": "150.25",
    "change": "2.50",
    "change_percent": "1.69%"
}
```

**Rate Limit**: 60/minute per user

---

#### Get Historical Data
```
GET /api/external/stocks/history/{symbol}
Authorization: Bearer {token}

Response (200):
{
    "2025-02-09": {
        "open": "145.50",
        "high": "152.00",
        "low": "145.00",
        "close": "150.25"
    },
    ...
}
```

**Rate Limit**: 60/minute per user

---

#### Search Stocks
```
GET /api/external/stocks/search?query=apple
Authorization: Bearer {token}

Response (200):
[
    {
        "symbol": "AAPL",
        "name": "Apple Inc.",
        "type": "equity"
    },
    ...
]
```

**Rate Limit**: 60/minute per user

---

#### Get Company Profile
```
GET /api/external/stocks/profile/{symbol}
Authorization: Bearer {token}

Response (200):
{
    "symbol": "AAPL",
    "companyName": "Apple Inc.",
    "industry": "Technology",
    "sector": "Technology",
    "ceo": "Tim Cook",
    ...
}
```

**Rate Limit**: 60/minute per user

---

## Services

### PortfolioService

**Namespace**: `App\Services\PortfolioService`

**Purpose**: Handles all portfolio-related business logic.

**Key Methods**:

```php
/**
 * Buy stocks for a user
 * 
 * @param User $user
 * @param string $stockSymbol
 * @param int $quantity
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
public function buyStock($user, string $stockSymbol, int $quantity): array
```

**Logic**:
1. Validate stock exists
2. Calculate total cost (price × quantity)
3. Check user has sufficient balance
4. Execute atomic transaction:
   - Deduct balance
   - Update/create portfolio entry with new average price
   - Record transaction
   - Award 10 XP
   - Check for level up if XP ≥ (level × 1000)
5. Return success response

---

```php
/**
 * Sell stocks for a user
 * 
 * @param User $user
 * @param string $stockSymbol
 * @param int $quantity
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
public function sellStock($user, string $stockSymbol, int $quantity): array
```

**Logic**:
1. Validate stock exists
2. Check user owns sufficient shares
3. Calculate proceeds (price × quantity)
4. Execute atomic transaction:
   - Add proceeds to balance
   - Update portfolio quantity
   - Record transaction
   - Award 10 XP
   - Check for level up
5. Return success response with proceeds

---

### StockApiService

**Namespace**: `App\Services\StockApiService`

**Purpose**: Wrapper around Alpha Vantage API for stock data.

**Configuration**:
- API Key: `config('services.alphavantage.key')`
- Base URL: `https://www.alphavantage.co/query`
- Cache Duration: 5 minutes (quotes), 24 hours (history)

**Key Methods**:

```php
/**
 * Get real-time stock quote
 * 
 * @param string $symbol
 * @return array|null ['symbol' => string, 'price' => float, 'change' => float, 'change_percent' => string]
 */
public function getQuote(string $symbol): ?array
```

**Features**:
- Caches results to minimize API calls
- Gracefully handles API failures
- Logs errors for debugging

---

```php
/**
 * Get historical price data
 * 
 * @param string $symbol
 * @param string $outputSize ('compact' or 'full')
 * @return array|null
 */
public function getHistoricalData(string $symbol, string $outputSize = 'compact'): ?array
```

**Features**:
- Returns daily OHLCV (open, high, low, close, volume) data
- 'compact' returns last 100 days
- 'full' returns complete history
- 24-hour caching

---

### FinancialModelingPrepService

**Namespace**: `App\Services\FinancialModelingPrepService`

**Purpose**: Integration with Financial Modeling Prep API for additional market data.

**Configuration**:
- API Key: `config('services.fmp.key')`

**Typical Usage**: Stock seeding, company information, market overview

---

## Controllers

### AuthController

**Namespace**: `App\Http\Controllers\Api\AuthController`

**Endpoints**:
- `register(Request $request)` → POST /api/auth/register
- `login(Request $request)` → POST /api/auth/login
- `logout(Request $request)` → POST /api/auth/logout
- `user(Request $request)` → GET /api/auth/user

**Key Logic**:

**Register**:
- Validates input (name, email, password with confirmation)
- Creates user with starting balance $10,000
- Generates Sanctum API token
- Returns user and token

**Login**:
- Validates credentials
- Throws `ValidationException` on failure
- Generates Sanctum API token

---

### PortfolioController

**Namespace**: `App\Http\Controllers\Api\PortfolioController`

**Endpoints**:
- `index()` → GET /api/portfolio
- `summary()` → GET /api/portfolio/summary
- `buyStock(Request $request)` → POST /api/portfolio/buy
- `sellStock(Request $request)` → POST /api/portfolio/sell

**Key Logic**:

**index()**: Returns user's portfolio entries with relationships loaded.

**summary()**: Calculates:
- Total invested (sum of all average_price × quantity)
- Current portfolio value (sum of current_price × quantity)
- Cash balance
- Unrealized gain/loss

**buyStock()**: Delegates to `PortfolioService::buyStock()`

**sellStock()**: Delegates to `PortfolioService::sellStock()`

---

### StockController

**Namespace**: `App\Http\Controllers\Api\StockController`

**Endpoints**:
- `index()` → GET /api/stocks
- `show(string $symbol)` → GET /api/stocks/{symbol}
- `history(string $symbol)` → GET /api/stocks/{symbol}/history

**Key Logic**:

**index()**: Returns paginated list of all stocks.

**show()**: Returns single stock with loaded relationships (portfolio, history).

**history()**: Returns historical price data for a stock.

---

### ExternalStockController

**Namespace**: `App\Http\Controllers\Api\ExternalStockController`

**Endpoints**:
- `quote(string $symbol)` → GET /api/external/stocks/quote/{symbol}
- `history(string $symbol)` → GET /api/external/stocks/history/{symbol}
- `search(Request $request)` → GET /api/external/stocks/search
- `profile(string $symbol)` → GET /api/external/stocks/profile/{symbol}

**Key Logic**: All methods use service classes to fetch from external APIs.

---

### AchievementController

**Namespace**: `App\Http\Controllers\Api\AchievementController`

**Endpoints**:
- `index()` → GET /api/achievements
- `leaderboard()` → GET /api/leaderboard

**Key Logic**:

**index()**: Returns achievements unlocked by authenticated user with pivot data.

**leaderboard()**: Queries users ranked by level and XP, includes portfolio values.

---

### UiModeController

**Namespace**: `App\Http\Controllers\UiModeController`

**Endpoints**:
- `toggleUiMode()` → GET /toggle-ui
- `setUiMode(string $mode)` → GET /set-ui/{mode}

**Purpose**: Switch between normal and senior UI modes.

---

## Authentication

### Authentication Method: Laravel Sanctum

**Token Type**: Bearer tokens

**How It Works**:
1. User registers or logs in → server generates token
2. Client stores token securely (localStorage, secure storage)
3. Client includes token in `Authorization: Bearer {token}` header
4. Server validates token via `auth:sanctum` middleware
5. `request->user()` returns authenticated user

**Token Generation**:
```php
$token = $user->createToken('auth-token')->plainTextToken;
```

**Token Revocation**:
```php
$request->user()->currentAccessToken()->delete();
```

### Protected Routes

All routes under the `middleware('auth:sanctum')` group require valid token:
- `/api/portfolio/*`
- `/api/achievements`
- `/api/leaderboard`
- `/api/auth/logout`
- `/api/auth/user`
- `/api/external/*`

### Public Routes

No authentication required:
- `/api/stocks`
- `/api/stocks/{symbol}`
- `/api/stocks/{symbol}/history`
- `/api/auth/register`
- `/api/auth/login`
- All web routes

---

## Gamification System

### Experience Points (XP)

**Earning XP**:
- Buy stock: +10 XP
- Sell stock: +10 XP
- Unlock achievement: Varies by achievement

**Level Progression**:
- Formula: `XP_needed_for_next_level = current_level * 1000`
- Level 1 → Level 2: 1,000 XP
- Level 2 → Level 3: 2,000 XP
- Level 3 → Level 4: 3,000 XP
- etc.

**User Attributes**:
```php
$user->level          // Current level (starts at 1)
$user->experience_points // XP toward next level
```

---

### Achievements

**Purpose**: Unlockable badges that reward and motivate users.

**Achievement Data**:
- `name`: Display name
- `description`: What to do to earn
- `icon`: Image/icon identifier
- `xp_reward`: XP granted when unlocked
- `requirement_type`: Type of condition (e.g., 'first_trade', 'portfolio_value')
- `requirement_value`: Threshold value

**Achievement Pivot Data**:
- `unlocked_at`: Timestamp when user earned achievement

**Example Requirements**:
- First Trade: Make any buy transaction
- Portfolio Millionaire: Reach $1,000,000 portfolio value
- Trader's Dozen: Own stocks in 12+ different companies

---

### Leaderboard

**Ranking**:
1. Sorted by level (descending)
2. Then by experience points (descending)
3. Includes portfolio value for context

**Data Per User**:
- Rank
- Name
- Level
- Experience Points
- Achievement Count
- Current Portfolio Value

---

## External API Integration

### Alpha Vantage API

**Service Class**: `App\Services\StockApiService`

**Features**:
- Real-time stock quotes
- Historical daily price data
- Market overview

**API Endpoints Used**:
- `GLOBAL_QUOTE`: Real-time quote
- `TIME_SERIES_DAILY`: Historical data

**Rate Limits**:
- Free tier: 5 API calls per minute
- Premium: Higher limits available

**Implementation Detail**: The application uses 12-second delays between calls to respect rate limits.

**Error Handling**:
- Failed requests logged to `storage/logs/`
- Graceful null return on failure
- Cached results used when available

---

### Financial Modeling Prep (FMP)

**Service Class**: `App\Services\FinancialModelingPrepService`

**Features**:
- Company profiles
- Stock search
- Additional financial data

**Configuration**:
```php
'fmp' => [
    'key' => env('FMP_API_KEY'),
]
```

---

### Configuration

**File**: `.env`

```
ALPHAVANTAGE_API_KEY=your_key_here
FMP_API_KEY=your_key_here
```

**Setup**:
1. Register at https://www.alphavantage.co (free)
2. Register at https://site.financialmodelingprep.com (free)
3. Add keys to `.env`
4. Keys are loaded via `config/services.php`

---

## Console Commands

### UpdateStockPrices

**Command**: `php artisan stocks:update-prices`

**Purpose**: Update current stock prices from Alpha Vantage API.

**Logic**:
1. Fetches all stocks
2. Queries Alpha Vantage for current quote
3. Updates `current_price` and `change_percentage`
4. Respects API rate limits (12-second delay per call)

**Usage**:
```bash
php artisan stocks:update-prices
```

**Typical Cron Job**:
```
0 9-16 * * 1-5 php /path/to/artisan stocks:update-prices
```
(Runs every hour during market hours on weekdays)

---

### ImportStockHistory

**Command**: `php artisan stocks:import-history`

**Purpose**: Import historical price data into `stock_history` table.

**Logic**:
1. Fetches historical data from Alpha Vantage
2. Stores daily OHLCV data
3. Used for charting and analysis

---

### SeedStocksFromFmp

**Command**: `php artisan stocks:seed-from-fmp`

**Purpose**: Populate database with stocks from Financial Modeling Prep.

**Logic**:
1. Queries FMP for list of tradeable stocks
2. Extracts symbol, name, category
3. Creates or updates stock records

---

### GameMarketTick

**Command**: `php artisan game:market-tick`

**Purpose**: Simulate realistic market movements (for testing/demo).

**Logic**:
1. Updates stock prices randomly
2. Creates realistic percentage movements
3. Used for development/demo when no real API data available

---

## Setup and Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 16+ and npm
- SQLite or MySQL (configurable)

### Installation Steps

1. **Clone repository**:
```bash
git clone https://github.com/yourusername/investment-gamified.git
cd investment-gamified
```

2. **Install dependencies**:
```bash
composer install
npm install
```

3. **Setup environment**:
```bash
cp .env.example .env
php artisan key:generate
```

4. **Create database**:
```bash
touch database/database.sqlite  # For SQLite
# OR configure .env for MySQL/PostgreSQL
```

5. **Run migrations**:
```bash
php artisan migrate
```

6. **Seed initial data** (optional):
```bash
php artisan db:seed
```

7. **Build frontend assets**:
```bash
npm run build
# OR for development with watch:
npm run dev
```

8. **Configure API keys** in `.env`:
```
ALPHAVANTAGE_API_KEY=your_key
FMP_API_KEY=your_key
```

9. **Start development server**:
```bash
php artisan serve
```

10. **Access application**:
- Web: http://localhost:8000
- API: http://localhost:8000/api

### One-Command Setup

As defined in `composer.json`:
```bash
composer run setup
```

This runs:
1. `composer install`
2. Copies `.env.example` to `.env`
3. Generates app key
4. Runs migrations
5. `npm install`
6. Builds frontend assets

---

## Configuration

### Key Configuration Files

**`config/services.php`**:
```php
'alphavantage' => [
    'key' => env('ALPHAVANTAGE_API_KEY'),
],
'fmp' => [
    'key' => env('FMP_API_KEY'),
],
```

**`config/database.php`**:
- Database connection (SQLite, MySQL, PostgreSQL)
- Connection credentials

**`config/app.php`**:
- Application timezone
- Locale
- Providers

**`.env` (Environment Variables)**:
```
APP_NAME=Investment Gamified
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# DB_CONNECTION=mysql
# DB_HOST=localhost
# DB_PORT=3306
# DB_DATABASE=investment_gamified
# DB_USERNAME=root
# DB_PASSWORD=

CACHE_DRIVER=file
QUEUE_CONNECTION=sync

ALPHAVANTAGE_API_KEY=
FMP_API_KEY=
```

---

## Development Workflow

### Local Development

**Terminal 1 - Start PHP server**:
```bash
php artisan serve
```

**Terminal 2 - Build assets (watch mode)**:
```bash
npm run dev
```

**Terminal 3 - Optional: Watch logs**:
```bash
php artisan pail
```

**Combined (one command)**:
```bash
composer run dev
```

This uses `concurrently` to run all three processes.

---

### Running Tests

**Run all tests**:
```bash
composer test
```

**Run specific test file**:
```bash
php artisan test tests/Feature/AuthRoutesTest.php
```

**Run with coverage**:
```bash
php artisan test --coverage
```

---

### Code Quality

**Code formatting with Pint**:
```bash
./vendor/bin/pint
```

**Check code without fixing**:
```bash
./vendor/bin/pint --test
```

---

### Database

**Run migrations**:
```bash
php artisan migrate
```

**Rollback last batch**:
```bash
php artisan migrate:rollback
```

**Fresh database (caution - deletes all data)**:
```bash
php artisan migrate:fresh
php artisan migrate:fresh --seed
```

**Create migration**:
```bash
php artisan make:migration create_table_name
```

**Tinker (interactive shell)**:
```bash
php artisan tinker
>>> User::first()
>>> Stock::all()
>>> $user = User::find(1); $user->balance
```

---

## Best Practices

### Code Organization

1. **Controllers**: Keep thin, delegate to services
   ```php
   // Good
   public function buy(Request $request)
   {
       return $this->portfolioService->buyStock(
           auth()->user(),
           $request->symbol,
           $request->quantity
       );
   }
   
   // Avoid
   public function buy(Request $request)
   {
       // 100 lines of complex logic
   }
   ```

2. **Services**: Encapsulate business logic
   ```php
   // Services should be single-responsibility
   class PortfolioService
   {
       public function buyStock(...) { ... }
       public function sellStock(...) { ... }
   }
   ```

3. **Models**: Define relationships and attribute casts
   ```php
   protected $casts = [
       'balance' => 'decimal:2',
   ];
   ```

4. **Routes**: Clearly organize by feature/API version
   ```php
   Route::prefix('api')->group(function () {
       // API routes here
   });
   ```

### Database

1. **Always use transactions** for multi-step operations:
   ```php
   DB::transaction(function () {
       // Operations here are atomic
   });
   ```

2. **Use migrations** for all schema changes - never modify tables manually

3. **Add indexes** for frequently queried columns:
   ```php
   $table->index(['user_id', 'created_at']);
   ```

4. **Use foreign keys** with constraints:
   ```php
   $table->foreignId('user_id')->constrained()->onDelete('cascade');
   ```

### API Design

1. **Consistent response format**:
   ```json
   {
       "success": true/false,
       "message": "...",
       "data": { ... }
   }
   ```

2. **Use appropriate HTTP status codes**:
   - 200: Success
   - 201: Resource created
   - 400: Bad request
   - 401: Unauthorized
   - 404: Not found
   - 422: Validation failed
   - 500: Server error

3. **Validate input** at route level:
   ```php
   $request->validate([
       'email' => 'required|email|unique:users',
       'quantity' => 'required|integer|min:1',
   ]);
   ```

### Security

1. **Always hash passwords**:
   ```php
   'password' => Hash::make($value)
   ```

2. **Use Sanctum tokens** for API auth (not session-based)

3. **Validate user ownership**:
   ```php
   if ($portfolio->user_id !== auth()->id()) {
       abort(403);
   }
   ```

4. **Rate limit external API calls** to prevent quota abuse

5. **Log sensitive operations** for audit trail

### Performance

1. **Eager load relationships**:
   ```php
   // Good
   User::with('portfolios', 'transactions')->get()
   
   // Avoid (N+1 query problem)
   users.foreach(user => user.portfolios)
   ```

2. **Use pagination** for large result sets:
   ```php
   User::paginate(15)
   ```

3. **Cache API responses**:
   ```php
   Cache::remember('cache_key', now()->addMinutes(5), function () {
       return $apiCall();
   });
   ```

4. **Index database columns** used in WHERE, JOIN, ORDER BY

---

## Future Enhancements

### Potential Features

1. **Real-time Updates**:
   - WebSocket integration for live price updates
   - Broadcasting achievements and trades

2. **Portfolio Analytics**:
   - Performance charts and graphs
   - Risk analysis
   - Diversification recommendations

3. **Social Features**:
   - Follow other traders
   - Share portfolios
   - Discussion forums

4. **Advanced Trading**:
   - Limit orders (buy/sell at specific prices)
   - Dividend tracking
   - Tax-loss harvesting
   - Portfolio rebalancing strategies

5. **Enhanced Gamification**:
   - Seasonal challenges
   - Trading tournaments
   - Community achievements
   - Customizable avatars and profiles

6. **Mobile App**:
   - Native iOS/Android apps
   - Offline portfolio tracking
   - Push notifications

7. **Educational Content**:
   - Tutorials and guides
   - Stock analysis tools
   - Glossary of financial terms

8. **Admin Dashboard**:
   - User management
   - Achievement configuration
   - Stock management
   - Analytics and reporting

### Architecture Improvements

1. **Repository Pattern**: Abstract database queries further
2. **Event System**: Use Laravel events for better decoupling
3. **Queued Jobs**: Move heavy operations to queue system
4. **API Versioning**: Support v1, v2, etc. endpoints
5. **GraphQL**: Alternative to REST API
6. **Testing**: Expand test coverage to 80%+
7. **Documentation**: Add API documentation with Swagger
8. **Logging**: Enhanced structured logging and monitoring

---

## Troubleshooting

### Common Issues

**Issue**: "Stock API returns null"
- **Cause**: API key missing or invalid
- **Solution**: Check `.env` file has correct `ALPHAVANTAGE_API_KEY`

**Issue**: "Insufficient balance" error when buying
- **Cause**: User cash balance is less than order cost
- **Solution**: Ensure new users start with $10,000; check portfolio summary

**Issue**: "Stock not found" when trading
- **Cause**: Stock symbol doesn't exist in database
- **Solution**: Seed stocks using `php artisan db:seed` or `php artisan stocks:seed-from-fmp`

**Issue**: Rate limit errors from Alpha Vantage
- **Cause**: Too many API calls (free: 5/min)
- **Solution**: Ensure commands use sleep(12) between calls; use cache

**Issue**: Database migrations fail
- **Cause**: Incorrect column types or constraints
- **Solution**: Run `php artisan migrate:rollback` then `migrate`

---

## Summary

Investment Gamified is a feature-rich investment simulation platform built with Laravel and modern JavaScript. Its modular architecture makes it easy to extend, while comprehensive authentication and business logic services ensure data integrity and user engagement through gamification.

### Key Strengths

✅ Clean separation of concerns (controllers, services, models)
✅ Secure API authentication with Sanctum tokens
✅ Comprehensive gamification system (levels, XP, achievements)
✅ Real-time stock data integration
✅ Responsive UI with normal and senior modes
✅ Strong database design with proper constraints
✅ Well-documented codebase

### Quick Start Checklist

- [ ] Clone repository
- [ ] Run `composer run setup`
- [ ] Configure `.env` with API keys
- [ ] Run `php artisan serve` and `npm run dev`
- [ ] Test registration at `/api/auth/register`
- [ ] Access web UI and test buying/selling stocks

---

**Last Updated**: February 2026
**Version**: 1.0
**Maintained By**: Development Team
