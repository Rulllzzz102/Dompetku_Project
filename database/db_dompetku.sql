CREATE DATABASE IF NOT EXISTS db_dompetku
	CHARACTER SET utf8mb4
	COLLATE utf8mb4_unicode_ci;

USE db_dompetku;

CREATE TABLE IF NOT EXISTS users (
	user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	username VARCHAR(50) NOT NULL,
	email VARCHAR(191) NOT NULL,
	password_hash VARCHAR(255) NOT NULL,
	display_name VARCHAR(100) NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id),
	UNIQUE KEY uq_users_username (username),
	UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
	account_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	account_name VARCHAR(100) NOT NULL,
	account_type ENUM('cash', 'bank', 'ewallet', 'other') NOT NULL DEFAULT 'cash',
	initial_balance DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (account_id),
	UNIQUE KEY uq_accounts_user_name (user_id, account_name),
	KEY idx_accounts_user_active (user_id, is_active),
	CONSTRAINT fk_accounts_user
		FOREIGN KEY (user_id) REFERENCES users (user_id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
	category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NULL,
	category_name VARCHAR(100) NOT NULL,
	category_type ENUM('income', 'expense') NOT NULL,
	icon VARCHAR(50) NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (category_id),
	UNIQUE KEY uq_categories_user_name_type (user_id, category_name, category_type),
	KEY idx_categories_type_active (category_type, is_active),
	CONSTRAINT fk_categories_user
		FOREIGN KEY (user_id) REFERENCES users (user_id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
	transaction_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	account_id INT UNSIGNED NULL,
	category_id INT UNSIGNED NULL,
	transaction_type ENUM('income', 'expense') NOT NULL,
	title VARCHAR(150) NOT NULL,
	amount DECIMAL(15,2) UNSIGNED NOT NULL,
	transaction_date DATE NOT NULL,
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (transaction_id),
	KEY idx_transactions_user_date (user_id, transaction_date),
	KEY idx_transactions_user_type_date (user_id, transaction_type, transaction_date),
	KEY idx_transactions_category (category_id),
	KEY idx_transactions_account (account_id),
	CONSTRAINT fk_transactions_user
		FOREIGN KEY (user_id) REFERENCES users (user_id)
		ON DELETE CASCADE,
	CONSTRAINT fk_transactions_account
		FOREIGN KEY (account_id) REFERENCES accounts (account_id)
		ON DELETE SET NULL,
	CONSTRAINT fk_transactions_category
		FOREIGN KEY (category_id) REFERENCES categories (category_id)
		ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
	budget_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	category_id INT UNSIGNED NOT NULL,
	period_month TINYINT UNSIGNED NOT NULL,
	period_year SMALLINT UNSIGNED NOT NULL,
	limit_amount DECIMAL(15,2) UNSIGNED NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (budget_id),
	UNIQUE KEY uq_budgets_user_category_period (user_id, category_id, period_year, period_month),
	CONSTRAINT fk_budgets_user
		FOREIGN KEY (user_id) REFERENCES users (user_id)
		ON DELETE CASCADE,
	CONSTRAINT fk_budgets_category
		FOREIGN KEY (category_id) REFERENCES categories (category_id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (user_id, category_name, category_type, icon)
SELECT NULL, defaults.category_name, defaults.category_type, defaults.icon
FROM (
	SELECT 'Gaji' AS category_name, 'income' AS category_type, 'payments' AS icon
	UNION ALL SELECT 'Bonus', 'income', 'redeem'
	UNION ALL SELECT 'Investasi', 'income', 'trending_up'
	UNION ALL SELECT 'Makanan & Minuman', 'expense', 'restaurant'
	UNION ALL SELECT 'Transportasi', 'expense', 'directions_car'
	UNION ALL SELECT 'Belanja', 'expense', 'shopping_bag'
	UNION ALL SELECT 'Tagihan & Utilitas', 'expense', 'receipt_long'
	UNION ALL SELECT 'Hiburan', 'expense', 'movie'
	UNION ALL SELECT 'Kesehatan', 'expense', 'health_and_safety'
	UNION ALL SELECT 'Lainnya', 'expense', 'more_horiz'
) AS defaults
WHERE NOT EXISTS (
	SELECT 1
	FROM categories AS existing
	WHERE existing.user_id IS NULL
	  AND existing.category_name = defaults.category_name
	  AND existing.category_type = defaults.category_type
);
