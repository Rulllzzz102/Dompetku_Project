<?php

function getUserTransactions(mysqli $connection, int $userId, string $type = "all", int $limit = 200): array
{
	$limit = max(1, min($limit, 500));
	$sql = "SELECT t.transaction_id, t.title, t.amount, t.transaction_date, t.transaction_type,
				   c.category_name, a.account_name
			FROM transactions AS t
			LEFT JOIN categories AS c ON c.category_id = t.category_id
			LEFT JOIN accounts AS a ON a.account_id = t.account_id
			WHERE t.user_id = ?";

	if ($type === "income" || $type === "expense") {
		$sql .= " AND t.transaction_type = ?";
	} else {
		$type = "all";
	}

	$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC LIMIT " . $limit;
	$statement = mysqli_prepare($connection, $sql);
	if (!$statement) {
		return [];
	}

	if ($type === "all") {
		mysqli_stmt_bind_param($statement, "i", $userId);
	} else {
		mysqli_stmt_bind_param($statement, "is", $userId, $type);
	}

	mysqli_stmt_execute($statement);
	$result = mysqli_stmt_get_result($statement);
	$transactions = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
	mysqli_stmt_close($statement);

	return $transactions;
}

function getUserTransaction(mysqli $connection, int $transactionId, int $userId): ?array
{
	$statement = mysqli_prepare(
		$connection,
		"SELECT transaction_type, title, amount, transaction_date, category_id, account_id, notes
		 FROM transactions WHERE transaction_id = ? AND user_id = ? LIMIT 1"
	);
	if (!$statement) {
		return null;
	}

	mysqli_stmt_bind_param($statement, "ii", $transactionId, $userId);
	mysqli_stmt_execute($statement);
	$result = mysqli_stmt_get_result($statement);
	$transaction = $result ? mysqli_fetch_assoc($result) : null;
	mysqli_stmt_close($statement);

	return $transaction ?: null;
}

function getTransactionCategories(mysqli $connection, int $userId, string $type): array
{
	if ($type !== "income" && $type !== "expense") {
		return [];
	}

	$statement = mysqli_prepare(
		$connection,
		"SELECT category_id, category_name, icon FROM categories
		 WHERE category_type = ? AND is_active = 1 AND (user_id IS NULL OR user_id = ?)
		 ORDER BY user_id IS NOT NULL, category_name"
	);
	if (!$statement) {
		return [];
	}

	mysqli_stmt_bind_param($statement, "si", $type, $userId);
	mysqli_stmt_execute($statement);
	$result = mysqli_stmt_get_result($statement);
	$categories = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
	mysqli_stmt_close($statement);

	return $categories;
}

function getUserAccounts(mysqli $connection, int $userId): array
{
	$statement = mysqli_prepare(
		$connection,
		"SELECT account_id, account_name FROM accounts
		 WHERE user_id = ? AND is_active = 1 ORDER BY account_name"
	);
	if (!$statement) {
		return [];
	}

	mysqli_stmt_bind_param($statement, "i", $userId);
	mysqli_stmt_execute($statement);
	$result = mysqli_stmt_get_result($statement);
	$accounts = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
	mysqli_stmt_close($statement);

	return $accounts;
}

function userOwnsTransactionCategory(mysqli $connection, int $categoryId, int $userId, string $type): bool
{
	$statement = mysqli_prepare(
		$connection,
		"SELECT category_id FROM categories
		 WHERE category_id = ? AND category_type = ? AND is_active = 1
		   AND (user_id IS NULL OR user_id = ?) LIMIT 1"
	);
	if (!$statement) {
		return false;
	}

	mysqli_stmt_bind_param($statement, "isi", $categoryId, $type, $userId);
	mysqli_stmt_execute($statement);
	mysqli_stmt_store_result($statement);
	$isValid = mysqli_stmt_num_rows($statement) === 1;
	mysqli_stmt_close($statement);

	return $isValid;
}

function userOwnsActiveAccount(mysqli $connection, int $accountId, int $userId): bool
{
	$statement = mysqli_prepare(
		$connection,
		"SELECT account_id FROM accounts WHERE account_id = ? AND user_id = ? AND is_active = 1 LIMIT 1"
	);
	if (!$statement) {
		return false;
	}

	mysqli_stmt_bind_param($statement, "ii", $accountId, $userId);
	mysqli_stmt_execute($statement);
	mysqli_stmt_store_result($statement);
	$isValid = mysqli_stmt_num_rows($statement) === 1;
	mysqli_stmt_close($statement);

	return $isValid;
}

function createUserTransaction(
	mysqli $connection,
	int $userId,
	?int $accountId,
	int $categoryId,
	string $type,
	string $title,
	string $amount,
	string $transactionDate,
	string $notes
): bool {
	$statement = mysqli_prepare(
		$connection,
		"INSERT INTO transactions
			(user_id, account_id, category_id, transaction_type, title, amount, transaction_date, notes)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
	);
	if (!$statement) {
		return false;
	}

	mysqli_stmt_bind_param(
		$statement,
		"iiisssss",
		$userId,
		$accountId,
		$categoryId,
		$type,
		$title,
		$amount,
		$transactionDate,
		$notes
	);
	$created = mysqli_stmt_execute($statement);
	mysqli_stmt_close($statement);

	return $created;
}

function updateUserTransaction(
	mysqli $connection,
	int $transactionId,
	int $userId,
	?int $accountId,
	int $categoryId,
	string $type,
	string $title,
	string $amount,
	string $transactionDate,
	string $notes
): bool {
	$statement = mysqli_prepare(
		$connection,
		"UPDATE transactions
		 SET account_id = ?, category_id = ?, transaction_type = ?, title = ?, amount = ?, transaction_date = ?, notes = ?
		 WHERE transaction_id = ? AND user_id = ?"
	);
	if (!$statement) {
		return false;
	}

	mysqli_stmt_bind_param(
		$statement,
		"iisssssii",
		$accountId,
		$categoryId,
		$type,
		$title,
		$amount,
		$transactionDate,
		$notes,
		$transactionId,
		$userId
	);
	$updated = mysqli_stmt_execute($statement);
	mysqli_stmt_close($statement);

	return $updated;
}

function deleteUserTransaction(mysqli $connection, int $transactionId, int $userId): bool
{
	$statement = mysqli_prepare(
		$connection,
		"DELETE FROM transactions WHERE transaction_id = ? AND user_id = ?"
	);
	if (!$statement) {
		return false;
	}

	mysqli_stmt_bind_param($statement, "ii", $transactionId, $userId);
	$deleted = mysqli_stmt_execute($statement) && mysqli_stmt_affected_rows($statement) > 0;
	mysqli_stmt_close($statement);

	return $deleted;
}
