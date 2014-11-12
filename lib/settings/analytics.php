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

		add_action( 'load-' . self::$pagehook, function () {
			add_action( 'add_meta_boxes_' . \Podlove\Settings\Analytics::$pagehook, function () {
				add_meta_box( \Podlove\Settings\Analytics::$pagehook . '_release_downloads_chart', __( 'Downloads over Time', 'podlove' ), '\Podlove\Settings\Analytics::chart', \Podlove\Settings\Analytics::$pagehook, 'normal' );		
				add_meta_box( \Podlove\Settings\Analytics::$pagehook . '_numbers', __( 'Download Numbers', 'podlove' ), '\Podlove\Settings\Analytics::numbers', \Podlove\Settings\Analytics::$pagehook, 'normal' );		
			} );
			do_action( 'add_meta_boxes_' . \Podlove\Settings\Analytics::$pagehook );

			wp_enqueue_script( 'postbox' );
		} );
	}

	public function scripts_and_styles() {
		if ( ! isset( $_REQUEST['page'] ) )
			return;

		if ( $_REQUEST['page'] != 'podlove_analytics' )
			return;

		wp_register_script('podlove-d3-js',          \Podlove\PLUGIN_URL . '/js/admin/d3.min.js');
		wp_register_script('podlove-crossfilter-js', \Podlove\PLUGIN_URL . '/js/admin/crossfilter.min.js');
		wp_register_script('podlove-dc-js',          \Podlove\PLUGIN_URL . '/js/admin/dc.js', array('podlove-d3-js', 'podlove-crossfilter-js'));
	
		wp_enqueue_script('podlove-dc-js');

		wp_register_style( 'podlove-dc-css', \Podlove\PLUGIN_URL . '/css/dc.css', array(), \Podlove\get_plugin_header( 'Version' ) );
		wp_enqueue_style( 'podlove-dc-css' );
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

	public function view_template() {
		?>

		<h2><?php echo __("Podcast Analytics", "podlove"); ?></h2>

		<div style="width: 100%; height: 260px">
			<div id="total-chart"></div>
			<div id="month-chart"></div>
		</div>
		
		<?php 
		$table = new \Podlove\Downloads_List_Table();
		$table->prepare_items();
		$table->display();
		?>

		<script type="text/javascript">
		(function ($) {

			var dateFormat = d3.time.format("%Y-%m-%d %H");

			d3.csv(ajaxurl + "?action=podlove-analytics-downloads-per-day", function(data) {
				data.forEach(function(d) {
					d.dd = dateFormat.parse(d.date);
					d.downloads = +d.downloads;
					d.month = dateFormat.parse(d.date).getMonth()+1;
					d.year = dateFormat.parse(d.date).getFullYear();
				});
				
				var ndx = crossfilter(data);
				var all = ndx.groupAll();

				// var downloadsDim = ndx.dimension(function(d){ return d.downloads; });
				var dateDim = ndx.dimension(function(d){ return d.dd; });
				var downloadsTotal = dateDim.group().reduceSum(dc.pluck("downloads"));

				var minDate = dateDim.bottom(1)[0].dd;
				var maxDate = dateDim.top(1)[0].dd;

				var totalDownloadsChart = dc.barChart("#total-chart")
					.width(800).height(250)
					.dimension(dateDim)
					.group(downloadsTotal)
					.x(d3.time.scale().domain([minDate,maxDate]))
					.elasticX(true)
					.centerBar(true)
					.gap(1)
					.brushOn(false)
					.yAxisLabel("Total Downloads per day")
				;

				totalDownloadsChart.yAxis().tickFormat(function(v) {
					if (v < 1000)
						return v;
					else
						return (v/1000) + "k";
				});

				var monthDim  = ndx.dimension(function(d) {return +d.month;});
				var monthTotal = monthDim.group().reduceSum(function(d) {return d.downloads;});

				var monthRingChart = dc.pieChart("#month-chart")
				    .width(150).height(150)
				    .dimension(monthDim)
				    .group(monthTotal)
				    .innerRadius(30)
				    .label(function(d) {
				    	switch (d.key) {
				    	  case 1:
				    	    return "Jan"; break;
				    	  case 2:
				    	    return "Feb"; break;
				    	  case 3:
				    	    return "Mar"; break;
				    	  case 4:
				    	    return "Apr"; break;
				    	  case 5:
				    	    return "May"; break;
				    	  case 6:
				    	    return "Jun"; break;
				    	  case 7:
				    	    return "Jul"; break;
				    	  case 8:
				    	    return "Aug"; break;
				    	  case 9:
				    	    return "Sep"; break;
				    	  case 10:
				    	    return "Oct"; break;
				    	  case 11:
				    	    return "Nov"; break;
				    	  case 12:
				    	    return "Dec"; break;
				    	}
				    });

				dc.renderAll();

			});

		})(jQuery);
		</script>

		<?php
	}

	public static function numbers() {

		$episode = Model\Episode::find_one_by_id((int) $_REQUEST['episode']);

		$cache = \Podlove\Cache\TemplateCache::get_instance();
		echo $cache->cache_for('podlove_analytics_episode' . $episode->id, function() use ($episode) {
	
			$post = get_post( $episode->post_id );

			$releaseDate = new \DateTime($post->post_date);
			$releaseDate->setTime(0, 0, 0);

			$diff = $releaseDate->diff(new \DateTime());
			$daysSinceRelease = $diff->days;

			$downloads = array(
				'total'     => Model\DownloadIntentClean::total_by_episode_id($episode->id, "1000 years ago", "now"),
				'month'     => Model\DownloadIntentClean::total_by_episode_id($episode->id, "28 days ago", "yesterday"),
				'week'      => Model\DownloadIntentClean::total_by_episode_id($episode->id, "7 days ago", "yesterday"),
				'yesterday' => Model\DownloadIntentClean::total_by_episode_id($episode->id, "1 day ago"),
				'today'     => Model\DownloadIntentClean::total_by_episode_id($episode->id, "now")
			);

			$peak = Model\DownloadIntentClean::peak_download_by_episode_id($episode->id);

			ob_start();
			?>

			<div class="analytics-metric-box">
				<span class="analytics-description">Average</span>
				<span class="analytics-value"><?php echo number_format_i18n($downloads['total'] / ($daysSinceRelease+1), 1) ?></span>
				<span class="analytics-subtext">Downloads per Day</span>
			</div>

			<div class="analytics-metric-box">
				<span class="analytics-description">Peak</span>
				<span class="analytics-value"><?php echo number_format_i18n($peak['downloads']) ?></span>
				<span class="analytics-subtext">Downloads<br>on <?php echo mysql2date(get_option('date_format'), $peak['theday']) ?></span>
			</div>

			<div class="analytics-metric-box">
				<span class="analytics-description">Total</span>
				<span class="analytics-value"><?php echo number_format_i18n($downloads['total']) ?></span>
				<span class="analytics-subtext">Downloads</span>
			</div>

			<div class="analytics-metric-box">
				<table>
					<tbody>
						<tr>
							<td>28 Days</td>
							<td><?php echo number_format_i18n($downloads['month']) ?></td>
						</tr>
						<tr>
							<td>7 Days</td>
							<td><?php echo number_format_i18n($downloads['week']) ?></td>
						</tr>
						<tr>
							<td>Yesterday</td>
							<td><?php echo number_format_i18n($downloads['yesterday']) ?></td>
						</tr>
						<tr>
							<td>Today</td>
							<td><?php echo number_format_i18n($downloads['today']) ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<style type="text/css">
			.analytics-metric-box {
				text-align: center;
				float: left;
				margin: 20px 0 20px 20px;
			}
			.analytics-metric-box > span {
				font-size: 23px;
				line-height: 23px;
				display: block;
			}

			.analytics-metric-box .analytics-value {
				font-weight: bold;
				line-height: 40px;
			}

			.analytics-metric-box .analytics-description,
			.analytics-metric-box .analytics-subtext {
				font-size: 14px;
				line-height: 16px;
				color: #666;
			}
			</style>

			<div class="clear"></div>
			<?php

			$html = ob_get_contents();
			ob_end_clean();
			return $html;

		}, 600); // 10 minutes

	}

	public function show_template() {
		$episode = Model\Episode::find_one_by_id((int) $_REQUEST['episode']);
		$post    = get_post( $episode->post_id );

		$releaseDate = new \DateTime($post->post_date);
		$releaseDate->setTime(0, 0, 0);

		$diff = $releaseDate->diff(new \DateTime());
		$daysSinceRelease = $diff->days;

		?>

		<h2>
			<?php echo $post->post_title ?>
			<br><small>
				<?php echo sprintf(
							"Released on %s (%d days ago)",
							mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $post->post_date),
							number_format_i18n($daysSinceRelease)
						) ?>
			</small>
		</h2>

		<style type="text/css">
		h2 small {
			color: #666;
		}
		</style>

		<div id="poststuff" class="metabox-holder">

			<!-- main -->
			<div id="post-body">
				<div id="post-body-content">
					<?php do_meta_boxes( self::$pagehook, 'normal', NULL ); ?>					
				</div>
			</div>

			<br class="clear"/>

		</div>

		<!-- Stuff for opening / closing metaboxes -->
		<script type="text/javascript">
		jQuery( document ).ready( function( $ ){
			// close postboxes that should be closed
			$( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );
			// postboxes setup
			postboxes.add_postbox_toggles( '<?php echo self::$pagehook; ?>' );
		} );
		</script>

		<form style='display: none' method='get' action=''>
			<?php
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
			?>
		</form>

		<?php
	}

	public static function chart() {
		$episode = Model\Episode::find_one_by_id((int) $_REQUEST['episode']);
		$post    = get_post( $episode->post_id );

		?>
		<div id="chart-grouping-selection">
			<span style="line-height: 26px">Unit</span>
			<a href="#" data-hours="1" class="button button-secondary">1h</a>
			<a href="#" data-hours="2" class="button button-secondary">2h</a>
			<a href="#" data-hours="3" class="button button-secondary">3h</a>
			<a href="#" data-hours="4" class="button button-secondary">4h</a>
			<a href="#" data-hours="6" class="button button-secondary">6h</a>
			<a href="#" data-hours="12" class="button button-secondary">12h</a>
			<a href="#" data-hours="24" class="button button-secondary">1d</a>
			<a href="#" data-hours="168" class="button button-secondary">1w</a>
			<a href="#" data-hours="672" class="button button-secondary">4w</a>
		</div>

		<div 
			id="episode-performance-chart" 
			data-episode="<?php echo $episode->id ?>"
			style="float: none"
			>
		</div>

		<div id="episode-range-chart" style="float: none"></div>
		
		<div id="episode-weekday-chart" style="float: left"></div>
		<div id="episode-asset-chart" style="float: left"></div>
		<div id="episode-client-chart" style="float: left"></div>
		<div id="episode-system-chart" style="float: left"></div>

		<div style="clear: both"></div>


		<script type="text/javascript">
		var assetNames = <?php
			$assets = Model\EpisodeAsset::all();
			echo json_encode(
				array_combine(
					array_map(function($a) { return $a->id; }, $assets),
					array_map(function($a) { return $a->title; }, $assets)
				)
			);
		?>;

		(function ($) {
			var csvCurEpisodeRawData, csvAvgEpisodeRawData;

			var $chart = jQuery("#episode-performance-chart");

			var dateFormat     = d3.time.format("%Y-%m-%d");
			var dateTimeFormat = d3.time.format("%Y-%m-%d %H:%M:%S");

			var episode_id           = $chart.data("episode");

			var chart_width = $("#episode-performance-chart").closest(".inside").width();
			var brush = { min: null, max: null };

			function hour_format (hours) {
				var days = 0, weeks = 0, label = [];

				if (hours > 48) {
					days  = (hours - hours % 24) / 24;
					hours = hours % 24;
				}

				if (days > 13) {
					weeks = (days - days % 7) / 7;
					days  = days % 7;
				};

				if (weeks)
					label.push(weeks + "w");

				if (days)
					label.push(days + "d");

				if (hours)
					label.push(hours + "h")

				return label.join(" ");
			}

			var reduceAddFun = function (p, v) {
				
				p.downloads += v.downloads;

				p.weekday  = v.weekday;
				p.asset_id = v.asset_id;
				p.date     = v.date;
				p.client   = v.client;
				p.system   = v.system;

				return p;
			};
			var reduceSubFun = function (p, v) { 
				p.downloads -= v.downloads;
				return p;
			};
			var reduceBaseFun = function () {
				return {
					downloads: 0,
					weekday: 0,
					asset_id: 0,
					date: 0,
					client: "",
					system: ""
				};
			};

			var formatThousands = function(v) {
				if (v < 1000)
					return v;
				else
					return (v/1000) + "k";
			};

			function render_episode_performance_chart(options) {
				var hours_per_unit = options.hours_per_unit;

				var ndx1 = crossfilter(csvCurEpisodeRawData);

				var aggregatedDateDim = ndx1.dimension(function(d){ return Math.floor(d.hoursSinceRelease / hours_per_unit); });

				// chart 1: current episode
				// var curDownloadsTotal = aggregatedDateDim.group().reduceSum(dc.pluck("downloads"));
				var curDownloadsTotal = aggregatedDateDim.group().reduce(reduceAddFun, reduceSubFun, reduceBaseFun);

				// filter after grouping
				// @see http://stackoverflow.com/a/22018053/72448
				// var curFilteredDownloadsTotal = {
				//     all: function () {
				//         return curDownloadsTotal.all().filter(function(d) { return d.key < 7 * 24 / hours_per_unit; });
				//     }
				// }

				// chart 3: average episode
				var ndx3           = crossfilter(csvAvgEpisodeRawData);
				var avgDateDim     = ndx3.dimension(function(d){ return Math.floor(d.hoursSinceRelease / hours_per_unit); });
				var avgDownloadsTotal = avgDateDim.group().reduceSum(dc.pluck("downloads"));

				// var avgFilteredDownloadsTotal = {
				//     all: function () {
				//     	// console.log(avgDownloadsTotal.all());
				//         return avgDownloadsTotal.all().filter(function(d) { return d.key < 7 * 24 / hours_per_unit; });
				//     }
				// };

		        var cumulativeDownloadsTotal = aggregatedDateDim.group().reduceSum(dc.pluck("downloads")).all()
		                	.reduce(function (acc, cur) {
		                		if (acc.length) {
		        	        		cur.value += acc.slice(-1)[0].value;
		                		}
		                		acc.push(cur);
		                		return acc;
		                	}, [])
		        ;

				var filteredCumulativeDownloadsTotal = {
				    all: function () {
				        return cumulativeDownloadsTotal;
				    }
				};

				var dayOfWeek = ndx1.dimension(function (d) {
					return d.weekday;
			    });
			    var dayOfWeekGroup = dayOfWeek.group().reduce(reduceAddFun, reduceSubFun, reduceBaseFun);

				var curEpisodeDownloadsPerDayChart = dc.barChart(compChart)
					.dimension(aggregatedDateDim)
					.group(curDownloadsTotal, "Current Episode")
					.centerBar(true)
					.xAxisPadding(0.6)
					.renderTitle(true)
					.valueAccessor(function (v) {
						if (v.value) {
							return v.value.downloads;
						} else {
							return 0;
						}
					})

					.colors('#69B3FF')
				;

				var averageEpisodeChart = dc.lineChart(compChart)
					.dimension(avgDateDim)
					.group(avgDownloadsTotal, "Average Episode")
					.renderTitle(true)
					.colors('black')
				;

				var cumulativeEpisodeChart = dc.lineChart(compChart)
					.dimension(avgDateDim)
					.group(filteredCumulativeDownloadsTotal, "Cumulative")
					.colors('red')
					.useRightYAxis(true)
				;

				var rangeChart = dc.barChart("#episode-range-chart")
					.width(chart_width)
					.height(80)
					.dimension(aggregatedDateDim)
					.group(curDownloadsTotal)
					.x(d3.scale.linear().domain([0,100000]))
					.y(d3.scale.sqrt().domain([0,4000])) // FIXME 4000 must be filled i dynamically
					.elasticX(true)
					.centerBar(true)
					.xAxisPadding(0.6)
					.valueAccessor(function (v) {
						return v.value.downloads;
					})
					.colors('#69B3FF')
				;

				rangeChart.yAxis().ticks([2]);
				rangeChart.yAxis().tickFormat(formatThousands);

				rangeChart.xAxis().tickFormat(function(v) {
					return hour_format(v * hours_per_unit);
				});

				var compChart = dc.compositeChart("#episode-performance-chart")
					.width(chart_width)
					.height(250)
					.x(d3.scale.linear().domain([0,1000000]))
					.legend(dc.legend().x(chart_width - 160).y(20).itemHeight(13).gap(5))
					.elasticX(true)
					.elasticY(true)
					.brushOn(false)
					.yAxisLabel("Downloads")
					.xAxisLabel("Hours since release")
					.rangeChart(rangeChart)
					.title(function(d) {
						return [
							d.value.date ? d3.time.format("%Y-%m-%d")(d.value.date) : "",
							(d.key * hours_per_unit) + "h – " + ((d.key + 1) * hours_per_unit - 1) + "h after release",
							"Downloads: " + d.value.downloads
						].join("\n");
					})
					.compose([curEpisodeDownloadsPerDayChart, averageEpisodeChart, cumulativeEpisodeChart])
					.rightYAxisLabel("Monthly Index Fnord")
					;

				compChart.yAxis().tickFormat(formatThousands);

				compChart.xAxis().tickFormat(function(v) {
					return hour_format(v * hours_per_unit);
				});

				compChart.rightYAxis().tickFormat(formatThousands);

	        	var weekdayNames = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
				var weekdayChart = dc.rowChart("#episode-weekday-chart")
					.height(285)
					.width(285)
			        .margins({top: 0, left: 10, right: 10, bottom: 20})
			        .group(dayOfWeekGroup)
			        .dimension(dayOfWeek)
			        .elasticX(true)
			        .label(function (d) {
			        	return weekdayNames[d.key];
	                })
	                .title(function (d) {
                        return d.value.downloads;
                    })
                    .valueAccessor(function (v) {
                    	if (v.value) {
                    		return v.value.downloads;
                    	} else {
                    		return 0;
                    	}
                    })
                    .colors('#69B3FF')
				;

				weekdayChart.xAxis().tickFormat(formatThousands);

				var assets = ndx1.dimension(function (d) {
					return d.asset_id;
			    });
			    var assetsGroup = assets.group().reduce(reduceAddFun, reduceSubFun, reduceBaseFun);

				var assetChart = dc.rowChart("#episode-asset-chart")
					.width(285)
					.height(285)
					.margins({top: 0, left: 10, right: 10, bottom: 20})
					// .labelOffsetX(0)
					// .labelOffsetY(-2)
					// .gap(20)
					.elasticX(true)
					.dimension(assets) // set dimension
					.group(assetsGroup) // set group
					.valueAccessor(function (v) {
						if (v.value) {
							return v.value.downloads;
						} else {
							return 0;
						}
					})
					.ordering(function (v) {
						return -v.value.downloads;
					})
			        .label(function (d) {
			        	return assetNames[d.key];
	                })
	                .title(function (d) {
	                    return d.value.downloads;
	                })
	                .colors('#69B3FF')
			   ;

			   assetChart.xAxis().tickFormat(formatThousands);

			   	var clients = ndx1.dimension(function (d) {
			   		return d.client;
			    });
				var clientsGroup = clients.group().reduce(reduceAddFun, reduceSubFun, reduceBaseFun).order(function(v) {
					return v.downloads;
				});

			   var clientChart = dc.rowChart("#episode-client-chart")
					.width(285)
					.height(285)
					.margins({top: 0, left: 10, right: 10, bottom: 20})
					.elasticX(true)
					.dimension(clients)
					.group(clientsGroup)
					.valueAccessor(function (v) {
						return v.value.downloads;
					})
					.ordering(function (v) {
						return -v.value.downloads;
					})
					.othersGrouper(function(data) {
						return data; // no "others" group
					})
					.cap(10)
					.colors('#69B3FF')
				;

				clientChart.xAxis().tickFormat(formatThousands);

			   	var systems = ndx1.dimension(function (d) {
			   		return d.system;
			    });
				var systemsGroup = systems.group().reduce(reduceAddFun, reduceSubFun, reduceBaseFun).order(function(v) {
					return v.downloads;
				});

			   var systemChart = dc.rowChart("#episode-system-chart")
					.width(285)
					.height(285)
					.margins({top: 0, left: 10, right: 10, bottom: 20})
					.elasticX(true)
					.dimension(systems)
					.group(systemsGroup)
					.valueAccessor(function (v) {
						return v.value.downloads;
					})
					.ordering(function (v) {
						return -v.value.downloads;
					})
					.othersGrouper(function(data) {
						return data; // no "others" group
					})
					.cap(10)
					.colors('#69B3FF')
				;

				systemChart.xAxis().tickFormat(formatThousands);

				compChart.render();
				rangeChart.render();
				weekdayChart.render();
				assetChart.render();
				clientChart.render();
				systemChart.render();

				// set range from 0 to "one week" or "everything" if it is younger than a week

				if (!brush.min && !brush.max) {
					brush.min = 0;
					brush.max = 7*24;
				}

				rangeChart.brush()
					.extent([
						brush.min / hours_per_unit,
						Math.min(
							rangeChart.xUnitCount(),
							brush.max / hours_per_unit
						)
					])
					.event(rangeChart.select('g.brush'));

				rangeChart.brush().on('brushend', function(x,y,z) {
					extent = rangeChart.brush().extent();
					brush.min = extent[0] * hours_per_unit;
					brush.max = extent[1] * hours_per_unit;
				});
			}

			function load_episode_performance_chart(options) {

				if (csvCurEpisodeRawData) {
					render_episode_performance_chart(options);
				} else {
					$.when(
						$.ajax(ajaxurl + "?action=podlove-analytics-downloads-per-hour&episode=" + episode_id),
						$.ajax(ajaxurl + "?action=podlove-analytics-average-downloads-per-hour")
					).done(function(csvCurEpisode, csvAvgEpisode) {

						var csvMapper = function(d) {
							var parsed_date = dateFormat.parse(d.date);

							return {
								date: parsed_date,
								downloads: +d.downloads,
								hour: d3.time.hour(parsed_date),
								month: parsed_date.getMonth()+1,
								year: parsed_date.getFullYear(),
								// days: +d.days,
								weekday: parsed_date.getDay(),
								hoursSinceRelease: +d.hours_since_release,
								asset_id: +d.asset_id,
								client: d.client ? d.client : "Unknown",
								system: d.system ? d.system : "Unknown",
							};
						};

						var csvMapper1 = function(d) {
							return csvMapper(d);
						};

						csvCurEpisodeRawData = d3.csv.parse(csvCurEpisode[0], csvMapper1);
						csvAvgEpisodeRawData = d3.csv.parse(csvAvgEpisode[0], function(d) {
							return {
								hoursSinceRelease: +d.hoursSinceRelease,
								downloads: +d.downloads
							};
						});

						render_episode_performance_chart(options);
					});
				}

			}

			$("#chart-grouping-selection").on("click", "a", function(e) {
				var hours = parseInt($(this).data('hours'), 10);

				$(this).siblings().removeClass("active");
				$(this).addClass("active");

				load_episode_performance_chart({
					hours_per_unit: hours
				});

				e.preventDefault();
			});

			$("#chart-grouping-selection a:eq(3)").click();

		})(jQuery);
		</script>

		<style type="text/css">
		#episode-weekday-chart g.row text,
		#episode-client-chart g.row text,
		#episode-system-chart g.row text,
		#episode-asset-chart g.row text {
			fill: black;
		}
		</style>

		<?php
	}

}