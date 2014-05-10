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

		wp_register_script('podlove-highcharts-js', \Podlove\PLUGIN_URL . '/js/highcharts.js', array('jquery'));
		wp_enqueue_script('podlove-highcharts-js');
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

		$days = 28;

		$start = "$days days ago";
		$end   = "now";

		$startDay = date('Y-m-d', strtotime($start));
		$endDay   = date('Y-m-d', strtotime($end));

		$chartData = array(
			'days'  => Model\DownloadIntent::daily_episode_totals($episode->id, $start, $end),
			'title' => $post->post_title
		);

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
		
			$('#total_chart').highcharts({
			    chart: {
			        type: "column"
			    },
			    title: {
			        text: 'Downloads: 28 days'
			    },
			    subtitle: {
			        text: "<?php echo addslashes($chartData['title']) ?>",
			    },
			    xAxis: {
			        type: 'datetime',
			        //minRange: 14 * 24 * 3600000 // fourteen days
			    },
			    yAxis: {
			        title: {
			            text: 'Downloads'
			        }
			    },
			    legend: {
			        enabled: false
			    },
			    <?php 
			    $pointInterval = 24 * 3600 * 1000;
			    $pointStart    = strtotime($startDay) * 1000;
			    ?>
			    series: [{
			        name: "<?php echo addslashes($chartData['title']) ?>",
			        pointInterval: <?php echo $pointInterval ?>,
			        pointStart: <?php echo $pointStart ?>,
			        data: [
			            <?php echo implode(",", $chartData['days']) ?>
			        ]
			    }]
			});

		})(jQuery);
		</script>

		<?php
	}

	public function view_template() {
		$days = 28;

		$start = "$days days ago";
		$end   = "now";

		$startDay = date('Y-m-d', strtotime($start));
		$endDay   = date('Y-m-d', strtotime($end));

		$top_episode_ids = Model\DownloadIntent::top_episode_ids($start, $end);

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
			        type: 'datetime',
			        //minRange: 14 * 24 * 3600000 // fourteen days
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
			    ?>
			    series: [{
			        name: "<?php echo addslashes($top_episode_data[0]['title']) ?>",
			        pointInterval: <?php echo $pointInterval ?>,
			        pointStart: <?php echo $pointStart ?>,
			        data: [
			            <?php echo implode(",", $top_episode_data[0]['days']) ?>
			        ]
			    },{
			        name: "<?php echo addslashes($top_episode_data[1]['title']) ?>",
			        pointInterval: <?php echo $pointInterval ?>,
			        pointStart: <?php echo $pointStart ?>,
			        data: [
			            <?php echo implode(",", $top_episode_data[1]['days']) ?>
			        ]
			    },{
			        name: "<?php echo addslashes($top_episode_data[2]['title']) ?>",
			        pointInterval: <?php echo $pointInterval ?>,
			        pointStart: <?php echo $pointStart ?>,
			        data: [
			            <?php echo implode(",", $top_episode_data[2]['days']) ?>
			        ]
			    },{
			        name: "<?php echo addslashes($other_episode_data['title']) ?>",
			        pointInterval: <?php echo $pointInterval ?>,
			        pointStart: <?php echo $pointStart ?>,
			        data: [
			            <?php echo implode(",", $other_episode_data['days']) ?>
			        ]
			    }]
			});

		})(jQuery);
		</script>

		<?php
	}

}