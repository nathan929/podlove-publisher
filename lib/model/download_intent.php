<?php
namespace Podlove\Model;

class DownloadIntent extends Base {

	public static function top_episode_ids($start, $end = "now") {
		global $wpdb;

		$sql = "
			SELECT
				episode_id, COUNT(*) downloads
			FROM
				" . DownloadIntent::table_name() . " di
				JOIN " . MediaFile::table_name() . " mf ON mf.id = di.media_file_id
			WHERE
				" . self::sql_condition_from_time_strings($start, $end) . "
			GROUP BY
				episode_id
			ORDER BY
				downloads DESC
			LIMIT
				0, 3
		";

		return $wpdb->get_col(
			$wpdb->prepare($sql)
		);
	}

	public static function daily_episode_totals($episode_id, $start, $end = "now") {
		global $wpdb;

		$sql = "
			SELECT
				DATE(di.accessed_at) theday, COUNT(*) downloads
			FROM
				" . DownloadIntent::table_name() . " di
				JOIN " . MediaFile::table_name() . " mf ON mf.id = di.media_file_id
			WHERE
				episode_id = %d
				AND
				" . self::sql_condition_from_time_strings($start, $end) . "
			GROUP BY
				theday
			ORDER BY
				theday
		";

		$result = $wpdb->get_results(
			$wpdb->prepare($sql, $episode_id)
		);

		return self::days_data_from_query_result($result, $start, $end);;
	}

	public static function daily_totals($start, $end = "now", $exclude_episode_ids = array()) {
		global $wpdb;

		// ensure all values are ints
		$exclude_episode_ids = array_map(function($x) { return (int) $x; }, $exclude_episode_ids);
		// filter out zero values
		$exclude_episode_ids = array_filter($exclude_episode_ids);

		$exclude_sql = "";
		if (count($exclude_episode_ids)) {
			$exclude_sql = "episode_id NOT IN (" . implode(",", $exclude_episode_ids) . ") AND ";
		}

		$sql = "
			SELECT
				DATE(di.accessed_at) theday, COUNT(*) downloads
			FROM
				" . DownloadIntent::table_name() . " di
				JOIN " . MediaFile::table_name() . " mf ON mf.id = di.media_file_id
			WHERE
				$exclude_sql
				" . self::sql_condition_from_time_strings($start, $end) . "
			GROUP BY
				theday
			ORDER BY
				theday
		";

		$result = $wpdb->get_results(
			$wpdb->prepare($sql)
		);

		return self::days_data_from_query_result($result, $start, $end);
	}

	public static function total_by_episode_id($episode_id, $start = null, $end = null) {
		global $wpdb;

		$sql = "
			SELECT
				COUNT(*)
			FROM
				" . DownloadIntent::table_name() . " di
			WHERE
				media_file_id IN (
					SELECT id FROM " . MediaFile::table_name() . " WHERE episode_id = %d
				)
				AND " . self::sql_condition_from_time_strings($start, $end) . "
		";

		return $wpdb->get_var(
			$wpdb->prepare($sql, $episode_id)
		);
	}

	/**
	 * For an episode, get the day with the most downloads and the number of downloads.
	 * 
	 * @param  int $episode_id
	 * @return array with keys "downloads" and "theday"
	 */
	public function peak_download_by_episode_id($episode_id) {
		global $wpdb;

		$sql = "
			SELECT
				COUNT(*) downloads, DATE(accessed_at) theday
			FROM
				" . DownloadIntent::table_name() . " di
			WHERE
				media_file_id IN (
					SELECT id FROM " . MediaFile::table_name() . " WHERE episode_id = %d
				)
			GROUP BY theday
			ORDER BY downloads DESC
			LIMIT 0,1
		";

		return $wpdb->get_row(
			$wpdb->prepare($sql, $episode_id),
			ARRAY_A
		);
	}

	private static function days_data_from_query_result($totals, $start, $end) {

		$endDay = date("Y-m-d", strtotime($end));
			
		// use theday (date) as array key
		$dayTotals = array();
		foreach ($totals as $download) {
			$dayTotals[$download->theday] = $download->downloads;
		}

		// create 0-entries for days without downloads
		$days = array();
		$day = 0;

		do {
			$currentDay = date('Y-m-d', strtotime($start . " +$day days"));

			if (isset($dayTotals[$currentDay])) {
				$days[$currentDay] = $dayTotals[$currentDay];
			} else {
				$days[$currentDay] = 0;	
			}

			$day++;
		} while ($currentDay < $endDay);

		return $days;
	}

	/**
	 * Generate WHERE clause to a certain time range or day.
	 *
	 * If $start and $end are given, they describe a time range.
	 * If only $start is given, only data from this day will be returned.
	 * If none are given, there is no time restriction. "1 = 1" will be returned instead.
	 * 
	 * @param  string $start      Timerange start in words, or null. Default: null.
	 * @param  string $end        Timerange end in words, or null. Default: null.
	 * @param  string $tableAlias DownloadIntent table alias. Default: "di".
	 * @return string
	 */
	private static function sql_condition_from_time_strings($start = null, $end = null, $tableAlias = 'di') {

		$strToMysqlDate = function($s) { return date('Y-m-d', strtotime($s)); };

		if ($start && $end) {
			$timerange = "{$tableAlias}.accessed_at BETWEEN '{$strToMysqlDate($start)}' AND '{$strToMysqlDate($end)}'";
		} elseif ($start) {
			$timerange = "DATE({$tableAlias}.accessed_at) = '{$strToMysqlDate($start)}'";
		} else {
			$timerange = "1 = 1";
		}

		return $timerange;
	}

}

DownloadIntent::property( 'id', 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' );
DownloadIntent::property( 'user_agent_id', 'INT' );
DownloadIntent::property( 'media_file_id', 'INT' );
DownloadIntent::property( 'accessed_at', 'DATETIME' );
DownloadIntent::property( 'source', 'VARCHAR(255)' );
DownloadIntent::property( 'context', 'VARCHAR(255)' );
DownloadIntent::property( 'ip', 'VARCHAR(255)' );