"use strict";
//TODO write OO code

$.ajaxSetup({async: false});
//init global variables
var map;
var markers = [];

function initMap() {
  map = new google.maps.Map(document.getElementById('map'), {
	center: {lat: 51.516168, lng: -0.121519},
	zoom: 8
  });
}

/**
 * Main method to get NOTAMs
 * 
 * TODO - add validation
 *		- clean up existing validation errors
 * 
 * @param ICAO code value
 */
$('#get-notam').click(function(){
	deleteMarkers();//clear map from old markers
	var code = $('#code').val();
	var iconPath = '/images/warning-icon-th-x20.png';
	if (code.trim()) {
		$('.loading-ico').removeClass('invisible');
		$.getJSON('/getNotam/'+code, function( json ) {
			if (json.success) {
				//loop the Notam array
				$.each(json.data, function(index, nData){
					var myLatLng = new google.maps.LatLng(nData.lat, nData.lng);
					//centrilize map by first element in the list
					if (index === 0) {
						map.setCenter(myLatLng);
					}
					var infoWindow = new google.maps.InfoWindow({
							content: nData.message,
							maxWidth: 300
					});
					var marker = new google.maps.Marker({
						position: myLatLng,
						map: map,
						icon: iconPath,
						title: code
					});
					//add marker into global array
					markers.push(marker);

					google.maps.event.addListener(marker, 'click', function() {
						infoWindow.open(map, marker);
					});
				});

			} else {
				var messageBlock = '<div class="alert alert-warning">\
						<a href="#" class="close" data-dismiss="alert">&times;</a>\
						<div class="notam-message">'+ json.data +'</div>\
					</div>';
				$('.search-notam').after(messageBlock);
			}
			$('.loading-ico').addClass('invisible');
		}).error(function() {
			$('.loading-ico').addClass('invisible');
			var messageBlock = '<div class="alert alert-danger">\
						<a href="#" class="close" data-dismiss="alert">&times;</a>\
						<div class="notam-message">Error happened. Please verify if you entered correct ICAO code. Note: ICAO code value should have only four letters (e.g EGLL, EGGW, EGLF, ...).</div>\
					</div>';
			$('.search-notam').after(messageBlock);
		});
	}
});

// Sets the map on all markers in the array.
function setMapOnAll(map) {
  for (var i = 0; i < markers.length; i++) {
	markers[i].setMap(map);
  }
}

// Removes the markers from the map, but keeps them in the array.
function clearMarkers() {
  setMapOnAll(null);
}

/**
 * Deletes all markers in the array by removing references to them.
 * @returns {Boolean}
 */
function deleteMarkers() {
  clearMarkers();
  markers = [];

  return true;
}

$('#delete-markers').click(function(){
	deleteMarkers();
	//TODO show message for user
});

//API Authentication
function apiAuthenticate(){
	$.getJSON('/curlAPIauth', function( json ) {
		if (json.success) {
			var messageBlock = '<div class="alert alert-success">\
				<a href="#" class="close" data-dismiss="alert">&times;</a>\
				<p>'+ json.data +'</p>\
			</div>';
			$('.api-auth').after(messageBlock);
		} else {
			var messageBlock = '<div class="alert alert-danger">\
					<a href="#" class="close" data-dismiss="alert">&times;</a>\
					<p>'+ json.data +'</p>\
				</div>';
			$('.api-auth').after(messageBlock);
		}
	});
}