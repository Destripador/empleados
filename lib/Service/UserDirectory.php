<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCP\IUserManager;

/**
 * Resolves Nextcloud users through IUserManager instead of reading oc_users.
 *
 * Users from other backends (user_oidc, LDAP, ...) are not stored in oc_users,
 * so any JOIN against that table hides them or loses their display name.
 */
class UserDirectory {

	private IUserManager $userManager;

	/** @var array<string, ?string> */
	private array $displayNames = [];

	public function __construct(IUserManager $userManager) {
		$this->userManager = $userManager;
	}

	/**
	 * Display name of the user, or null when no backend knows the uid.
	 */
	public function getDisplayName(?string $uid): ?string {
		$uid = trim((string)$uid);
		if ($uid === '') {
			return null;
		}

		if (!array_key_exists($uid, $this->displayNames)) {
			$this->displayNames[$uid] = $this->userManager->getDisplayName($uid);
		}

		return $this->displayNames[$uid];
	}

	public function exists(?string $uid): bool {
		return $this->getDisplayName($uid) !== null;
	}

	/**
	 * Adds the `uid` and display name columns the old oc_users JOIN provided.
	 *
	 * With $onlyExisting the rows whose user no longer exists are dropped,
	 * which is what the previous INNER JOIN did. Otherwise the display name is
	 * left null for unknown users, like the previous LEFT JOIN.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	public function withDisplayNames(
		array $rows,
		string $uidKey = 'Id_user',
		string $nameKey = 'displayname',
		bool $onlyExisting = false,
	): array {
		$result = [];
		foreach ($rows as $row) {
			$uid = isset($row[$uidKey]) ? (string)$row[$uidKey] : null;
			$displayName = $this->getDisplayName($uid);
			if ($onlyExisting && $displayName === null) {
				continue;
			}

			if (!array_key_exists('uid', $row) || $row['uid'] === null) {
				$row['uid'] = $uid;
			}
			$row[$nameKey] = $displayName;
			$result[] = $row;
		}

		return $result;
	}

	/**
	 * Replacement for ORDER BY u.displayname.
	 *
	 * @template T of array<string, mixed>
	 * @param array<int, T> $rows
	 * @return list<T>
	 */
	public function sortByDisplayName(array $rows, string $nameKey = 'displayname'): array {
		usort($rows, static fn (array $a, array $b): int => strcasecmp(
			(string)($a[$nameKey] ?? ''),
			(string)($b[$nameKey] ?? '')
		));

		return $rows;
	}

	/**
	 * The uids of $uids whose display name contains $term, ignoring case.
	 *
	 * Replacement for a `u.displayname LIKE '%term%'` filter: the caller
	 * passes the candidate uids (e.g. the employees' Id_user values) and
	 * filters its query with `IN (...)`, so SQL paging stays correct.
	 *
	 * @param iterable<mixed> $uids
	 * @return list<string>
	 */
	public function filterUidsByDisplayName(iterable $uids, string $term): array {
		$term = trim($term);
		if ($term === '') {
			return [];
		}

		$matches = [];
		foreach ($uids as $uid) {
			$uid = trim((string)$uid);
			if ($uid === '' || isset($matches[$uid])) {
				continue;
			}

			$displayName = $this->getDisplayName($uid);
			if ($displayName !== null && mb_stripos($displayName, $term) !== false) {
				$matches[$uid] = $uid;
			}
		}

		return array_values($matches);
	}

	/**
	 * Every user known to any backend.
	 *
	 * @return list<array{uid: string, displayname: string}>
	 */
	public function listUsers(): array {
		$users = [];
		foreach ($this->userManager->search('') as $user) {
			$uid = $user->getUID();
			$displayName = $user->getDisplayName();
			$this->displayNames[$uid] = $displayName;
			$users[] = [
				'uid' => $uid,
				'displayname' => $displayName !== '' ? $displayName : $uid,
			];
		}

		return $this->sortByDisplayName($users);
	}
}
