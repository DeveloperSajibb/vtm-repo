-- ============================================================================
-- VTM Option PostgreSQL Database Schema
-- Migration: 001_initial_schema_postgresql.sql
-- Description: Complete database schema for VTM Option trading bot (PostgreSQL)
-- ============================================================================

-- Enable UUID extension (if needed)
-- CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================================================
-- USERS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    encrypted_api_token TEXT,
    api_token_created_at TIMESTAMP,
    api_token_last_used TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    is_admin SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active);
CREATE INDEX IF NOT EXISTS idx_users_is_admin ON users(is_admin);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);

-- ============================================================================
-- DERIV TOKENS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS deriv_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    token_hash CHAR(64) NOT NULL,
    encrypted_token TEXT NOT NULL,
    scopes JSONB,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (user_id, token_hash)
);

CREATE INDEX IF NOT EXISTS idx_deriv_tokens_token_hash ON deriv_tokens(token_hash);
CREATE INDEX IF NOT EXISTS idx_deriv_tokens_is_active ON deriv_tokens(is_active);
CREATE INDEX IF NOT EXISTS idx_deriv_tokens_last_used_at ON deriv_tokens(last_used_at);

-- ============================================================================
-- SETTINGS TABLE (User Trading Settings)
-- ============================================================================
CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL UNIQUE,
    stake DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    target DECIMAL(10, 2) NOT NULL DEFAULT 100.00,
    stop_limit DECIMAL(10, 2) NOT NULL DEFAULT 50.00,
    is_bot_active BOOLEAN DEFAULT FALSE,
    last_active_at TIMESTAMP,
    daily_profit DECIMAL(10, 2) DEFAULT 0.00,
    daily_loss DECIMAL(10, 2) DEFAULT 0.00,
    reset_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_settings_user_id ON settings(user_id);
CREATE INDEX IF NOT EXISTS idx_settings_is_bot_active ON settings(is_bot_active);
CREATE INDEX IF NOT EXISTS idx_settings_reset_date ON settings(reset_date);

-- ============================================================================
-- TRADING SESSIONS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS trading_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    state VARCHAR(50) NOT NULL DEFAULT 'initializing' 
        CHECK (state IN ('initializing', 'active', 'paused', 'stopping', 'stopped', 'error', 'recovering', 'expired')),
    
    -- Risk Parameters
    stake DECIMAL(10, 2) NOT NULL,
    target DECIMAL(10, 2) NOT NULL,
    stop_limit DECIMAL(10, 2) NOT NULL,
    max_active_contracts INTEGER DEFAULT 50,
    max_daily_trades INTEGER DEFAULT 0,
    
    -- Session Statistics
    total_trades INTEGER DEFAULT 0,
    successful_trades INTEGER DEFAULT 0,
    failed_trades INTEGER DEFAULT 0,
    total_profit DECIMAL(10, 2) DEFAULT 0.00,
    total_loss DECIMAL(10, 2) DEFAULT 0.00,
    daily_profit DECIMAL(10, 2) DEFAULT 0.00,
    daily_loss DECIMAL(10, 2) DEFAULT 0.00,
    daily_trade_count INTEGER DEFAULT 0,
    
    -- Session Activity Tracking
    start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP,
    duration INTEGER,
    
    -- API Token Information
    api_token_hash VARCHAR(255),
    api_token_created_at TIMESTAMP,
    
    -- Error Tracking
    error_count INTEGER DEFAULT 0,
    consecutive_errors INTEGER DEFAULT 0,
    last_error TEXT,
    last_error_time TIMESTAMP,
    
    -- Session Limits
    max_error_count INTEGER DEFAULT 5,
    max_inactive_time INTEGER DEFAULT 1800000, -- 30 minutes in milliseconds
    reset_date DATE NOT NULL,
    
    -- Session Metadata
    started_by VARCHAR(255) NOT NULL,
    stopped_by VARCHAR(255),
    stop_reason TEXT,
    metadata JSONB,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_trading_sessions_user_id ON trading_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_trading_sessions_session_id ON trading_sessions(session_id);
CREATE INDEX IF NOT EXISTS idx_trading_sessions_state ON trading_sessions(state);
CREATE INDEX IF NOT EXISTS idx_trading_sessions_start_time ON trading_sessions(start_time);
CREATE INDEX IF NOT EXISTS idx_trading_sessions_last_activity_time ON trading_sessions(last_activity_time);
CREATE INDEX IF NOT EXISTS idx_trading_sessions_reset_date ON trading_sessions(reset_date);
CREATE INDEX IF NOT EXISTS idx_trading_sessions_user_state ON trading_sessions(user_id, state);
CREATE INDEX IF NOT EXISTS idx_trading_sessions_user_start_time ON trading_sessions(user_id, start_time);
CREATE INDEX IF NOT EXISTS idx_trading_sessions_state_activity ON trading_sessions(state, last_activity_time);
CREATE INDEX IF NOT EXISTS idx_trading_sessions_user_state_activity ON trading_sessions(user_id, state, last_activity_time);

-- ============================================================================
-- TRADES TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS trades (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    session_id INTEGER,
    trade_id VARCHAR(255) NOT NULL UNIQUE,
    contract_id VARCHAR(255),
    asset VARCHAR(50) NOT NULL,
    direction VARCHAR(10) NOT NULL CHECK (direction IN ('RISE', 'FALL')),
    stake DECIMAL(10, 2) NOT NULL,
    payout DECIMAL(10, 2),
    profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'won', 'lost', 'cancelled')),
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP,
    duration INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES trading_sessions(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_trades_user_id ON trades(user_id);
CREATE INDEX IF NOT EXISTS idx_trades_session_id ON trades(session_id);
CREATE INDEX IF NOT EXISTS idx_trades_trade_id ON trades(trade_id);
CREATE INDEX IF NOT EXISTS idx_trades_status ON trades(status);
CREATE INDEX IF NOT EXISTS idx_trades_timestamp ON trades(timestamp);
CREATE INDEX IF NOT EXISTS idx_trades_asset ON trades(asset);
CREATE INDEX IF NOT EXISTS idx_trades_direction ON trades(direction);
CREATE INDEX IF NOT EXISTS idx_trades_user_timestamp ON trades(user_id, timestamp);
CREATE INDEX IF NOT EXISTS idx_trades_session_timestamp ON trades(session_id, timestamp);
CREATE INDEX IF NOT EXISTS idx_trades_user_session_timestamp ON trades(user_id, session_id, timestamp);

-- ============================================================================
-- SIGNALS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS signals (
    id SERIAL PRIMARY KEY,
    signal_type VARCHAR(10) NOT NULL CHECK (signal_type IN ('RISE', 'FALL')),
    asset VARCHAR(50),
    raw_text TEXT NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'api' CHECK (source IN ('api', 'file', 'manual')),
    source_ip VARCHAR(45),
    processed BOOLEAN NOT NULL DEFAULT FALSE,
    total_users INTEGER DEFAULT 0,
    successful_executions INTEGER DEFAULT 0,
    failed_executions INTEGER DEFAULT 0,
    execution_time INTEGER,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_signals_signal_type ON signals(signal_type);
CREATE INDEX IF NOT EXISTS idx_signals_asset ON signals(asset);
CREATE INDEX IF NOT EXISTS idx_signals_source ON signals(source);
CREATE INDEX IF NOT EXISTS idx_signals_processed ON signals(processed);
CREATE INDEX IF NOT EXISTS idx_signals_timestamp ON signals(timestamp);
CREATE INDEX IF NOT EXISTS idx_signals_processed_timestamp ON signals(processed, timestamp);
CREATE INDEX IF NOT EXISTS idx_signals_signal_type_timestamp ON signals(signal_type, timestamp);

-- ============================================================================
-- SESSIONS TABLE (JWT Token Sessions)
-- ============================================================================
CREATE TABLE IF NOT EXISTS sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    token VARCHAR(500) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);
CREATE INDEX IF NOT EXISTS idx_sessions_expires_at ON sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_sessions_user_expires ON sessions(user_id, expires_at);

-- ============================================================================
-- ADMIN USERS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_admin_users_username ON admin_users(username);
CREATE INDEX IF NOT EXISTS idx_admin_users_email ON admin_users(email);
CREATE INDEX IF NOT EXISTS idx_admin_users_is_active ON admin_users(is_active);

-- ============================================================================
-- ADMIN ACTIVITY LOGS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id SERIAL PRIMARY KEY,
    admin_user_id INTEGER NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_admin_activity_logs_admin_user_id ON admin_activity_logs(admin_user_id);
CREATE INDEX IF NOT EXISTS idx_admin_activity_logs_action ON admin_activity_logs(action);
CREATE INDEX IF NOT EXISTS idx_admin_activity_logs_created_at ON admin_activity_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_admin_activity_logs_admin_created ON admin_activity_logs(admin_user_id, created_at);

-- ============================================================================
-- API CALL LOGS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS api_call_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    status_code INTEGER,
    response_time INTEGER,
    request_data JSONB,
    response_data JSONB,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_api_call_logs_user_id ON api_call_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_api_call_logs_endpoint ON api_call_logs(endpoint);
CREATE INDEX IF NOT EXISTS idx_api_call_logs_method ON api_call_logs(method);
CREATE INDEX IF NOT EXISTS idx_api_call_logs_status_code ON api_call_logs(status_code);
CREATE INDEX IF NOT EXISTS idx_api_call_logs_created_at ON api_call_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_api_call_logs_user_created ON api_call_logs(user_id, created_at);

-- ============================================================================
-- SYSTEM SETTINGS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string' CHECK (setting_type IN ('string', 'number', 'boolean', 'json')),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_system_settings_setting_key ON system_settings(setting_key);

-- ============================================================================
-- INITIAL DATA / DEFAULTS
-- ============================================================================

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('app_name', 'VTM Option', 'string', 'Application name'),
('app_version', '1.0.0', 'string', 'Application version'),
('trading_enabled', 'true', 'boolean', 'Global trading enable/disable'),
('max_stake', '1000', 'number', 'Maximum stake amount allowed'),
('min_stake', '1', 'number', 'Minimum stake amount allowed'),
('default_stake', '1', 'number', 'Default stake amount'),
('default_target', '100', 'number', 'Default profit target'),
('default_stop_limit', '50', 'number', 'Default stop loss limit')
ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value;

-- ============================================================================
-- TRIGGER FUNCTION FOR UPDATED_AT
-- ============================================================================
-- PostgreSQL doesn't have ON UPDATE CURRENT_TIMESTAMP like MySQL
-- We need to create a trigger function to update the updated_at column

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply trigger to tables with updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_deriv_tokens_updated_at BEFORE UPDATE ON deriv_tokens
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_settings_updated_at BEFORE UPDATE ON settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_trading_sessions_updated_at BEFORE UPDATE ON trading_sessions
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_trades_updated_at BEFORE UPDATE ON trades
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_signals_updated_at BEFORE UPDATE ON signals
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_admin_users_updated_at BEFORE UPDATE ON admin_users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_system_settings_updated_at BEFORE UPDATE ON system_settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- VIEWS (Optional - for easier queries)
-- ============================================================================

-- View for user trading statistics
CREATE OR REPLACE VIEW user_trading_stats AS
SELECT 
    u.id AS user_id,
    u.email,
    COUNT(DISTINCT t.id) AS total_trades,
    COUNT(DISTINCT CASE WHEN t.status = 'won' THEN t.id END) AS won_trades,
    COUNT(DISTINCT CASE WHEN t.status = 'lost' THEN t.id END) AS lost_trades,
    COALESCE(SUM(CASE WHEN t.status = 'won' THEN t.profit ELSE 0 END), 0) AS total_profit,
    COALESCE(SUM(CASE WHEN t.status = 'lost' THEN ABS(t.profit) ELSE 0 END), 0) AS total_loss,
    COALESCE(SUM(t.profit), 0) AS net_profit,
    COALESCE(s.is_bot_active, FALSE) AS is_bot_active,
    COALESCE(s.daily_profit, 0) AS daily_profit,
    COALESCE(s.daily_loss, 0) AS daily_loss
FROM users u
LEFT JOIN trades t ON u.id = t.user_id
LEFT JOIN settings s ON u.id = s.user_id
GROUP BY u.id, u.email, s.is_bot_active, s.daily_profit, s.daily_loss;

-- View for active trading sessions
CREATE OR REPLACE VIEW active_trading_sessions AS
SELECT 
    ts.id,
    ts.user_id,
    u.email,
    ts.session_id,
    ts.state,
    ts.stake,
    ts.target,
    ts.stop_limit,
    ts.total_trades,
    ts.successful_trades,
    ts.failed_trades,
    ts.daily_profit,
    ts.daily_loss,
    ts.start_time,
    ts.last_activity_time,
    EXTRACT(EPOCH FROM (COALESCE(ts.end_time, NOW()) - ts.start_time))::INTEGER AS duration_seconds
FROM trading_sessions ts
INNER JOIN users u ON ts.user_id = u.id
WHERE ts.state IN ('initializing', 'active', 'recovering');

-- ============================================================================
-- STORED PROCEDURES (PostgreSQL Functions)
-- ============================================================================

-- Function to reset daily stats for all users
CREATE OR REPLACE FUNCTION reset_daily_stats()
RETURNS void AS $$
BEGIN
    UPDATE settings 
    SET daily_profit = 0, 
        daily_loss = 0,
        reset_date = CURRENT_DATE + INTERVAL '1 day'
    WHERE reset_date <= CURRENT_DATE;
    
    UPDATE trading_sessions
    SET daily_profit = 0,
        daily_loss = 0,
        daily_trade_count = 0,
        reset_date = CURRENT_DATE + INTERVAL '1 day'
    WHERE reset_date <= CURRENT_DATE;
END;
$$ LANGUAGE plpgsql;

-- Function to cleanup expired sessions
CREATE OR REPLACE FUNCTION cleanup_expired_sessions()
RETURNS void AS $$
BEGIN
    DELETE FROM sessions WHERE expires_at < NOW();
    
    UPDATE trading_sessions 
    SET state = 'expired' 
    WHERE state IN ('active', 'paused') 
    AND last_activity_time < NOW() - INTERVAL '30 minutes';
END;
$$ LANGUAGE plpgsql;

-- ============================================================================
-- TRIGGERS (Optional - for automatic operations)
-- ============================================================================

-- Trigger to update trade statistics in trading session
CREATE OR REPLACE FUNCTION update_session_stats_after_trade()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status != OLD.status AND NEW.session_id IS NOT NULL THEN
        IF NEW.status = 'won' THEN
            UPDATE trading_sessions
            SET successful_trades = successful_trades + 1,
                total_profit = total_profit + NEW.profit,
                daily_profit = daily_profit + NEW.profit,
                total_trades = total_trades + 1,
                daily_trade_count = daily_trade_count + 1
            WHERE id = NEW.session_id;
        ELSIF NEW.status = 'lost' THEN
            UPDATE trading_sessions
            SET failed_trades = failed_trades + 1,
                total_loss = total_loss + ABS(NEW.profit),
                daily_loss = daily_loss + ABS(NEW.profit),
                total_trades = total_trades + 1,
                daily_trade_count = daily_trade_count + 1
            WHERE id = NEW.session_id;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_session_stats_after_trade
AFTER UPDATE ON trades
FOR EACH ROW
WHEN (NEW.status IS DISTINCT FROM OLD.status)
EXECUTE FUNCTION update_session_stats_after_trade();

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================

