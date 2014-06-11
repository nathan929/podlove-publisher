<?php
namespace Podlove\Settings;

use \Podlove\Model;

class Analytics {
	
	static $pagehook;
	
	public function __construct( $handle ) {
		
		self::$pagehook = add_submenu_page(
			/* $parent_slug*/ $handle,
			/* $page_title */ __( 'Analytics', 'podlove' ),
			/* $menu_title */ __( 'Analytics', 'podlove' ),
			/* $capability */ 'administrator',
			/* $menu_slug  */ 'podlove_analytics',
			/* $function   */ array( $this, 'page' )
		);

		// add_action( 'admin_init', array( $this, 'process_form' ) );
		add_action( 'admin_init', array( $this, 'scripts_and_styles' ) );	
	}

	public function scripts_and_styles() {
		if ( ! isset( $_REQUEST['page'] ) )
			return;

		if ( $_REQUEST['page'] != 'podlove_analytics' )
			return;

		// wp_register_script('podlove-highcharts-js', \Podlove\PLUGIN_URL . '/js/highcharts.js', array('jquery'));
		// wp_enqueue_script('podlove-highcharts-js');

		wp_register_script('podlove-highstock-js', \Podlove\PLUGIN_URL . '/js/highstock.js', array('jquery'));
		wp_enqueue_script('podlove-highstock-js');
	}

	public function page() {

		?>
		<div class="wrap">
			<?php
			$action = ( isset( $_REQUEST['action'] ) ) ? $_REQUEST['action'] : NULL;
			switch ( $action ) {
				case 'show':
					$this->show_template();
					break;
				case 'index':
				default:
					$this->view_template();
					break;
			}
			?>
		</div>	
		<?php
	}

	public function show_template() {
		$episode = Model\Episode::find_one_by_id((int) $_REQUEST['episode']);
		$post    = get_post( $episode->post_id );

		$chartData = array(
			'days'  => Model\DownloadIntent::daily_episode_totals($episode->id, $post->post_date, "now"),
			'title' => $post->post_title
		);

		$releaseTime = strtotime($post->post_date);

		$topEpisodeIds = Model\DownloadIntent::top_episode_ids("1000 years ago", "now", 1);
		$topEpisodeId  = $topEpisodeIds[0];
		$topEpisode    = Model\Episode::find_one_by_id($topEpisodeId);
		$topPost       = get_post( $topEpisode->post_id );

		$topEpisodeData = array(
			'days' => Model\DownloadIntent::daily_episode_totals($topEpisodeId, $topPost->post_date, "now"),
			'title' => sprintf('Top Episode (%s)', get_the_title($topEpisode->post_id))
		);

		$mainEpisodeReleaseDate = new \DateTime($post->post_date);
		$topEpisodeReleaseDate  = new \DateTime($topPost->post_date);
		$differenceInDays = $mainEpisodeReleaseDate->diff($topEpisodeReleaseDate)->format('%a');

		$now = new \DateTime("now");

		$shiftedDays = array();
		foreach ($topEpisodeData['days'] as $date => $downloads) {
			$d = new \DateTime($date);
			$d->add(new \DateInterval('P' . $differenceInDays . 'D'));

			if ($d->diff($now)->format('%R') == "+") {
				$shiftedDays[$d->format('Y-m-d')] = $downloads;
			}
		}
		$topEpisodeData['days'] = $shiftedDays;

		?>
		<h2>
			<?php echo sprintf(
				__("Analytics: %s", "podlove"),
				$post->post_title
			);
			?>
		</h2>

		<div id="total_chart" style="min-width: 310px; height: 400px; margin: 0 auto"></div>

		<script type="text/javascript">
		(function ($) {
		
			$('#total_chart').highcharts('StockChart', {
				
				navigator: {
					enabled: true,
					series: {
						type: 'column',
						color: '#95CEFF',
						fillOpacity: 0.05
					}
				},

				legend: { enabled: true },
				
				rangeSelector: {
					selected: 1,
					buttons: [
						{
							type: 'week',
							count: 1,
							text: '1w'
						},
						{
							type: 'week',
							count: 4,
							text: '4w'
						},
						{
							type: 'year',
							count: 1,
							text: '1y'
						},
						{
							type: 'all',
							text: 'All'
						},
					]
				},

				yAxis: {
					title: { text: "Downloads" },
				},

				xAxis: {
					title: { text: "Days since Release" },
					labels: {
						formatter: function() {
							// days since release
							return Math.floor((this.value - <?php echo $releaseTime*1000 ?>) / 86400000);
						}
					}
				},
				
				series: [{
					type: 'column',
				    name: '<?php echo $chartData['title'] ?>',
				    data: [<?php
				    	echo implode(',', array_map(function($theday, $downloads) {
							list($y, $m, $d) = explode("-", $theday);
							return "[Date.UTC($y," . ($m-1) . ",$d)," . ((int) $downloads) . "]";
						}, array_keys($chartData['days']), array_values($chartData['days'])));
						?>]
				},{
					type: 'column',
				    name: '<?php echo $topEpisodeData['title'] ?>',
				    data: [<?php
				    	echo implode(',', array_map(function($theday, $downloads) {
							list($y, $m, $d) = explode("-", $theday);
							return "[Date.UTC($y," . ($m-1) . ",$d)," . ((int) $downloads) . "]";
						}, array_keys($topEpisodeData['days']), array_values($topEpisodeData['days'])));
						?>]
				}]

			});
		})(jQuery);
		</script>

		<?php 
		$releaseDate = new \DateTime($post->post_date);
		$diff        = $releaseDate->diff(new \DateTime());
		$daysSinceRelease = $diff->days;

		$downloads = array(
			'total'     => Model\DownloadIntent::total_by_episode_id($episode->id, "1000 years ago", "now"),
			'month'     => Model\DownloadIntent::total_by_episode_id($episode->id, "28 days ago", "now"),
			'week'      => Model\DownloadIntent::total_by_episode_id($episode->id, "7 days ago", "now"),
			'yesterday' => Model\DownloadIntent::total_by_episode_id($episode->id, "1 day ago"),
			'today'     => Model\DownloadIntent::total_by_episode_id($episode->id, "now")
		);

		$peak = Model\DownloadIntent::peak_download_by_episode_id($episode->id);
		?>

		<table>
			<tbody>
				<tr>
					<td>Total Downloads</td>
					<td><?php echo $downloads['total'] ?></td>
				</tr>
				<tr>
					<td>28 Days</td>
					<td><?php echo $downloads['month'] ?></td>
				</tr>
				<tr>
					<td>7 Days</td>
					<td><?php echo $downloads['week'] ?></td>
				</tr>
				<tr>
					<td>Yesterday</td>
					<td><?php echo $downloads['yesterday'] ?></td>
				</tr>
				<tr>
					<td>Today</td>
					<td><?php echo $downloads['today'] ?></td>
				</tr>
				<tr>
					<td>Release Date</td>
					<td><?php echo mysql2date(get_option('date_format'), $post->post_date) ?></td>
				</tr>
				<tr>
					<td>Peak Downloads/Day</td>
					<td><?php echo sprintf(
						"%d (%s)",
						$peak['downloads'],
						mysql2date(get_option('date_format'), $peak['theday'])
					) ?></td>
				</tr>
				<tr>
					<td>Average Downloads/Day</td>
					<td><?php echo $daysSinceRelease ? round($downloads['total'] / $daysSinceRelease, 1) : '—' ?></td>
				</tr>
				<tr>
					<td>Days since Release</td>
					<td><?php echo $daysSinceRelease ?></td>
				</tr>
			</tbody>
		</table>

		<?php
	}

	public function view_template() {
		$days = 28;

		$start = "$days days ago";
		$end   = "now";

		$startDay = date('Y-m-d', strtotime($start));
		$endDay   = date('Y-m-d', strtotime($end));

		$top_episode_ids = Model\DownloadIntent::top_episode_ids($start, $end, 3);

		$top_episode_data = array();
		foreach ($top_episode_ids as $episode_id) {
			$episode = Model\Episode::find_one_by_id($episode_id);
			$post = get_post($episode->post_id);

			$top_episode_data[] = array(
				'days'  => Model\DownloadIntent::daily_episode_totals($episode_id, $start, $end),
				'title' => $post->post_title
			);
		}

		$other_episode_data = array(
			'days'  => Model\DownloadIntent::daily_totals($start, $end, $top_episode_ids),
			'title' => "Other"
		);

		?>

		<h2><?php echo __("Podcast Analytics", "podlove"); ?></h2>

		<div id="total_chart" style="min-width: 310px; height: 400px; margin: 0 auto"></div>

		<?php
		$table = new \Podlove\Downloads_List_Table();
		$table->prepare_items();
		$table->display();
		?>

		<script type="text/javascript">
		(function ($) {
		
			$('#total_chart').highcharts({
			    chart: {
			        type: "column"
			    },
			    title: {
			        text: 'Downloads: 28 days'
			    },
			    subtitle: {
			        text: 'Top 3 episodes compared to the rest'
			    },
			    xAxis: {
			        type: 'datetime'
			    },
			    yAxis: {
			        title: {
			            text: 'Downloads'
			        }
			    },
			    plotOptions: {
			    	column: {
			    		stacking: "normal"
			    	}
			    },
			    legend: {
			        enabled: true
			    },
			    <?php 
			    $pointInterval = 24 * 3600 * 1000;
			    $pointStart    = strtotime($startDay) * 1000;
			    $dataFormat    = function($data) {
			    	return implode(",", $data);
			    };
			    ?>
			    series: [{
			        name: "<?php echo addslashes($top_episode_data[0]['title']) ?>",
			        pointInterval: <?php echo $pointInterval ?>,
			        pointStart: <?php echo $pointStart ?>,
			        data: [ <?php echo $dataFormat($top_episode_data[0]['days']); ?> ]
			    },{
			        name: "<?php echo addslashes($top_episode_data[1]['title']) ?>",
			        pointInterval: <?php echo $pointInterval ?>,
			        pointStart: <?php echo $pointStart ?>,
			        data: [ <?php echo $dataFormat($top_episode_data[1]['days']); ?> ]
			    },{
			        name: "<?php echo addslashes($top_episode_data[2]['title']) ?>",
			        pointInterval: <?php echo $pointInterval ?>,
			        pointStart: <?php echo $pointStart ?>,
			        data: [ <?php echo $dataFormat($top_episode_data[2]['days']); ?> ]
			    },{
			        name: "<?php echo addslashes($other_episode_data['title']) ?>",
			        pointInterval: <?php echo $pointInterval ?>,
			        pointStart: <?php echo $pointStart ?>,
			        data: [ <?php echo $dataFormat($other_episode_data['days']); ?> ]
			    }]
			});

		})(jQuery);
		</script>

		<?php
	}

}