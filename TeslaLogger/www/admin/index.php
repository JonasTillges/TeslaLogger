﻿<!DOCTYPE html>
<?php
require("language.php");
?>
<html lang="<?php echo $json_data["Language"]; ?>">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="apple-mobile-web-app-title" content="Teslalogger Config">
    <link rel="apple-touch-icon" href="img/apple-touch-icon.png">
    <title>Teslalogger Config V1.9</title>
	<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
	<link rel="stylesheet" href="https://teslalogger.de/teslalogger_style.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.4.0/dist/leaflet.css" integrity="sha512-puBpdR0798OZvTTbP4A8Ix/l+A4dHDD0DGqYW6RQ+9jxkRFclaxxQb/SJAWZfWAkuyeQUytO7+7N4QKrDh+drA==" crossorigin=""/>
   <!-- Make sure you put this AFTER Leaflet's CSS -->
	<script src="https://unpkg.com/leaflet@1.4.0/dist/leaflet.js" integrity="sha512-QVftwZFqvtRNi0ZyCtsznlKSWOStnDORoefr1enyq5mVL4tmKB3S/EnC3rRJcxCPavG10IcrVGSmPh6Qw5lwrg==" crossorigin=""></script>
	<script>
	var map = null;
	var marker = null;
	var mapInit = false;
	var loc;
	var LengthUnit = "<?php echo($LengthUnit); ?>";
	var TemperatureUnit = "<?php echo($TemperatureUnit); ?>";
	var PowerUnit = "<?php echo($PowerUnit); ?>";

  $( function() {
    $("button").button();
	GetCurrentData();

	map = new L.Map('map');
  // Define layers and add them to the control widget
    L.control.layers({
      'OpenStreetMap': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
      }).addTo(map), // Add default layer to map
      'OpenTopoMap': L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data: &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)',
        maxZoom: 17
      }),
      'Satellite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Imagery &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        // This map doesn't have labels so we force a label-only layer on top of it
        forcedOverlay: L.tileLayer('https://stamen-tiles-{s}.a.ssl.fastly.net/toner-labels/{z}/{x}/{y}.png', {
          attribution: 'Labels by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
          subdomains: 'abcd',
          maxZoom: 20,
        })
      })
    }).addTo(map);

	var greenIcon = L.icon({iconUrl: 'img/marker-icon-green.png', shadowUrl: 'https://unpkg.com/leaflet@1.4.0/dist/images/marker-shadow.png', iconAnchor:   [12, 40], popupAnchor:  [0, -25]});

	if (navigator.languages != undefined) loc = navigator.languages[0]; 
			else loc = navigator.language;

	setInterval(function()
		{
			if ( document.hasFocus() )
			{
				GetCurrentData();
			}
		}
		,5000);

	ShowInfo();
  } );

	function GetCurrentData()
	{
		$.ajax({
		  url: "current_json.php",
		  dataType: "json"
		  }).done(function( jsonData ) {
			if (LengthUnit == "mile")
			{
				$('#ideal_battery_range_km').text((jsonData["ideal_battery_range_km"] / 1.609).toFixed(1) + " mi");
				$('#odometer').text((jsonData["odometer"] / 1.609).toFixed(1) + " mi");
			}
			else
			{
				$('#ideal_battery_range_km').text(jsonData["ideal_battery_range_km"].toFixed(1) + " km");
				$('#odometer').text(jsonData["odometer"].toFixed(1) + " km");
			}

			$('#battery_level').text(jsonData["battery_level"]);
			var car_version = jsonData["car_version"];
			car_version = car_version.substring(0,car_version.lastIndexOf(" "));
			$('#car_version').text(car_version);

			if (jsonData["charging"])
			{
				$('#car_statusLabel').text("Wird geladen:");
				$('#car_status').html(jsonData["charger_power"] + " kW / +" + jsonData["charge_energy_added"] + " kWh<br>" + jsonData["charger_voltage"]+"V / " + jsonData["charger_actual_current"]+"A / "+ jsonData["charger_phases"]+"P");

				updateSMT(jsonData);
			}
			else if (jsonData["driving"])
			{
				$('#car_statusLabel').text("Fahren:");
				var str = "";
				if (LengthUnit == "mile")
					str = (jsonData["speed"]/ 1.609).toFixed(0) + " mph / "
				else
					str = jsonData["speed"] + " km/h / ";

				if (PowerUnit == "kw")
					str += (jsonData["power"] / 1.35962).toFixed(0) +"kW";
				else
					str += jsonData["power"]+"PS";

				$('#car_status').text(str);

				updateSMT(jsonData);
			}
			else if (jsonData["online"])
			{
				var text = "Online";

				if (jsonData["is_preconditioning"])
					text = text + "<br>Preconditioning " + jsonData["inside_temperature"] +"°C";

				if (jsonData["sentry_mode"])
					text = text + "<br>Sentry Mode";

				if (jsonData["battery_heater"])
					text = text + "<br>Battery Heater";

				$('#car_statusLabel').text("Status:");
				$('#car_status').html(text);

				updateSMT(jsonData);
			}
			else if (jsonData["sleeping"])
			{
				$('#car_statusLabel').text("Status:");
				$('#car_status').text("<?php t("Schlafen"); ?>");

				hideSMT();
			}
			else
			{
				$('#car_statusLabel').text("Status:");
				$('#car_status').text("Offline");

				hideSMT();
			}

			if (LengthUnit == "mile")
			{
				$("#max_speed").text((jsonData["trip_max_speed"]/ 1.609).toFixed(0));
				$("#lt_kmh").text("mph");

				$("#trip_avg_kwh").text(Math.round(jsonData["trip_avg_kwh"]* 1.609*10)/10);
				$("#lt_whkm").text("wh/mi");

				$("#trip_distance").text(Math.round(jsonData["trip_distance"]/ 1.609 *10)/10);
				$("#lt_trip_distance_km").text("mi");
			}
			else
			{
				$("#max_speed").text(jsonData["trip_max_speed"]);
				
				$("#trip_avg_kwh").text(Math.round(jsonData["trip_avg_kwh"] *10)/10);
				$("#trip_distance").text(Math.round(jsonData["trip_distance"]*10)/10);
			}

			if (PowerUnit == "kw")
			{
				$("#max_power").text((jsonData["trip_max_power"] / 1.35962).toFixed(0)); 				
				$("#lt_trip_PS").text("<?php t("kW"); ?>");
			}
			else
			{
				$("#max_power").text(jsonData["trip_max_power"]);
				$("#lt_trip_PS").text("<?php t("PS"); ?>");
			}


			$("#trip_start").text(jsonData["trip_start"]);
			$("#trip_kwh").text(Math.round(jsonData["trip_kwh"] *10)/10);
			
			var ts = new Date(Date.parse(jsonData["ts"]));
			$("#last_update").text(ts.toLocaleString(loc));

			var trip_duration_sec = jsonData["trip_duration_sec"];
			var min = Math.floor(trip_duration_sec / 60);
			var sec = trip_duration_sec % 60;
			if (sec < 10)
				sec = "0"+sec;

			$("#trip_duration_sec").text(min + ":" + sec);

			var p = L.latLng(parseFloat(jsonData["latitude"]), parseFloat(jsonData["longitude"]));

			if (!mapInit)
			{
				map.setView(p, <?php echo getZoomLevel(); ?>);
				mapInit = true;
			}
			else
				map.panTo(p);

			if (marker != null)
				map.removeLayer(marker)

			marker = L.marker(p);
			marker.addTo(map);
		});
	}

	function hideSMT()
	{
		$('#CellTempRow').hide();
		$('#BMSMaxChargeRow').hide();
		$('#BMSMaxDischargeRow').hide();
		$('#CellImbalanceRow').hide();
	}

	function updateSMT(jsonData)
	{
		if (jsonData["SMTCellTempAvg"])
		{
			$('#CellTempRow').show();
			$('#CellTemp').text(Math.round(jsonData["SMTCellTempAvg"] * 10)/10 + "°C");
		}
		else
		{
			$('#CellTempRow').hide();
		}

		if (jsonData["SMTBMSmaxCharge"])
		{
			$('#BMSMaxChargeRow').show();
			$('#BMSMaxCharge').text( Math.round(jsonData["SMTBMSmaxCharge"]) +" kW");
		}
		else
		{
			$('#BMSMaxChargeRow').hide();
		}

		if (jsonData["SMTBMSmaxDischarge"])
		{
			$('#BMSMaxDischargeRow').show();
			$('#BMSMaxDischarge').text( Math.round(jsonData["SMTBMSmaxDischarge"]) +" kW");
		}
		else
		{
			$('#BMSMaxDischargeRow').hide();
		}

		if (jsonData["SMTCellMaxV"] && jsonData["SMTCellMinV"])
		{
			var CellImbalance = Math.round((jsonData["SMTCellMaxV"] - jsonData["SMTCellMinV"]) * 1000);
			$('#CellImbalanceRow').show();
			$('#CellImbalance').text( CellImbalance +" mV");
		}
		else
		{
			$('#CellImbalanceRow').hide();
		}

	}

  function BackgroudRun($target, $text)
  {
	  $.ajax($target, {
		data: {
			id: ''
		}
		})
		.then(
		function success(name) {
			alert($text);
		},
		function fail(data, status) {
			alert($text);
		}
	);
  }
  
function ShowInfo()
{
	
	<?php
	if (!file_exists("/etc/teslalogger/sharedata.txt") && 
	!file_exists("/etc/teslalogger/nosharedata.txt") &&
	!file_exists("/tmp/sharedata.txt") && 
	!file_exists("/tmp/nosharedata.txt")
	)
	{?>
		$("#InfoText").html("<?php t("TextShare"); ?>");
		$(".HeaderT").show();
		$("#PositiveButton").click(function(){window.location.href='settings_share.php?a=yes';});
		$("#NegativeButton").click(function(){window.location.href='settings_share.php?a=no';});
	<?php
	}
	?>
	
}
  </script>
  </head>
  <body style="padding-top: 5px; padding-left: 10px;">
  <button style="width:120px;" onclick="window.location.href='logfile.php';">Logfile</button>
  <button style="width:120px;" onclick="BackgroudRun('restartlogger.php', 'Reboot!');">Restart</button>
  <button style="width:120px;" onclick="BackgroudRun('update.php', 'Reboot!');">Update</button>
  <button style="width:120px;" onclick="window.location.href='backup.php';">Backup</button>
  <button style="width:120px;" onclick="window.location.href='geofencing.php';">Geofence</button>
  <button style="width:120px;" onclick="BackgroudRun('/wakeup.php', 'Wakeup!');">Wakeup</button>
  <button style="width:120px;" onclick="BackgroudRun('gosleep.php', 'Sleep!');">Sleep</button>
  <button style="width:120px;" onclick="window.location.href='settings.php';">Settings</button>
  <br />
  <br />

  <div id="content" style="max-width:1036px;">
  <div id="info">
  <table class="HeaderT">
	  <thead><td colspan="2" class="HeaderStyle"><?php t("Info"); ?></td></thead>
	  <tr><td colspan="2"><span id="InfoText"></span></td></tr>
	  <tr><td></td><td style="float:right;"><button id="NegativeButton"><?php t("Nein"); ?></button> <button id="PositiveButton"><?php t("Ja"); ?></button></td></tr>
    </table>
  </div>
  <div style="float:left;">
	  <table class="b1 THeader">
	  <thead><td colspan="2" class="HeaderL HeaderStyle"><?php t("Fahrzeuginfo"); ?></td></thead>
	  <tr><td width="130px"><b><span id="car_statusLabel"></span></b></td><td width="180px"><span id="car_status"></span></td></tr>
	  <tr id='CellTempRow'><td><b><?php t("Cell Temp"); ?>:</b></td><td><span id="CellTemp"></span></td></tr>
	  <tr id='BMSMaxChargeRow'><td><b><?php t("Max Charge"); ?>:</b></td><td><span id="BMSMaxCharge"></span></td></tr>
	  <tr id='BMSMaxDischargeRow'><td><b><?php t("Max Discharge"); ?>:</b></td><td><span id="BMSMaxDischarge"></span></td></tr>
	  <tr id='CellImbalanceRow'><td><b><?php t("Cell Imbalance"); ?>:</b></td><td><span id="CellImbalance"></span></td></tr>
	  <tr><td><b><?php t("Typical Range"); ?>:</b></td><td><span id="ideal_battery_range_km">---</span> / <span id="battery_level">---</span> %</td></tr>
	  <tr><td><b><?php t("KM Stand"); ?>:</b></td><td><span id="odometer">---</span></td></tr>
	  <tr><td><b><?php t("Car Version"); ?>:</b></td><td><span id="car_version">---</span></td></tr>
	  <tr><td><b><?php t("Last Update"); ?>:</b></td><td><span id="last_update">---</span></td></tr>
	  <tr><td><b>Teslalogger:</b></td><td><?php checkForUpdates();?></td></tr>
    </table>

	  <table style="float:left;" class="THeader">
	  <thead><td colspan="2" class="HeaderL HeaderStyle"><?php t("Letzter Trip"); ?></td></thead>
	  <tr><td width="130px"><b>Start:</b></td><td width="180px"><span id="trip_start"></span></td></tr>
	  <tr><td><b><?php t("Dauer"); ?>:</b></td><td><span id="trip_duration_sec">---</span> min</td></tr>
	  <tr><td><b><?php t("Distanz"); ?>:</b></td><td><span id="trip_distance">---</span> <span id="lt_trip_distance_km">km</span></td></tr>
	  <tr><td><b><?php t("Verbrauch"); ?>:</b></td><td><span id="trip_kwh">---</span> kWh</td></tr>
	  <tr><td><b><?php t("Ø Verbrauch"); ?>:</b></td><td><span id="trip_avg_kwh">---</span> <span id="lt_whkm">Wh/km</span></td></tr>
	  <tr><td><b><?php t("Max km/h"); ?> / <?php t("PS"); ?>:</b></td><td><span id="max_speed">---</span> <span id="lt_kmh">km/h</span> / <span id="max_power">---</span> <span id="lt_trip_PS"><span></td></tr>
	  </table>
  </div>

  <table style="float:left;">
  <thead style="background-color:#d0d0d0; color:#000000;"><td colspan="2" style="font-weight:bold;"><?php t("Current Pos"); ?></td></thead>
  <tr><td width="680px"><div id="map" style="height: 400px;" /></td></tr>
  </table>

  <?php

  function checkForUpdates()
  {
	$installed = "?";

	if (file_exists("/etc/teslalogger/VERSION"))
		$installed = file_get_contents("/etc/teslalogger/VERSION");
	else
		$installed = getTeslaloggerVersion("/etc/teslalogger/git/TeslaLogger/Properties/AssemblyInfo.cs");

	$onlineversion = getTeslaloggerVersion("https://raw.githubusercontent.com/bassmaster187/TeslaLogger/master/TeslaLogger/Properties/AssemblyInfo.cs");

	if ($installed != $onlineversion)
	{
		echo($installed . "<br><b>Update available: " .$onlineversion."</b>");
	}
	else
	{
		echo($installed);
	}
  }

function getTeslaloggerVersion($path)
{
	$f = file_get_contents($path);
	preg_match('/AssemblyVersion\(\"([0-9\.]+)\"/',$f, $matches);
	return $matches[1];
}
function getZoomLevel()
{
	if (file_exists("/etc/teslalogger/settings.json"))
	{
		$content = file_get_contents("/etc/teslalogger/settings.json");
		$j = json_decode($content);
		if (!empty($j->{"ZoomLevel"})) 
			return $j->{"ZoomLevel"};	
	}
	
	return 15;
}
?>

  <?PHP
  global $language;

  if (isset($language) && strlen($language) > 1 && $language != "de")
	echo(file_get_contents("https://teslalogger.de/teslalogger_content_index-".$language.".php"));
  else
	echo(file_get_contents("https://teslalogger.de/teslalogger_content_index.php"));

  ?>
  </div>
  </body>
</html>
