var urlParams; //array of url parameters for rebuilding the url
var facets; //array of currently-selected facets

$(document).ready(function() {
	$(function() {
		$("#facetbox").draggable();
	});
	
	urlParams = getUrlParameters();
	facets = new Array();
	
	//extract properties from printouts parameter
	if (urlParams["po"] != null) {
		urlParams["po"].replace(/%3F(.+)%0D%0A/gi, function(m,facet) {
			facets.push(facet.replace(' ', '_'));
		});
	}
	
	//remove [[Property:+]] statements
	if (urlParams["q"] != null) {
		urlParams["q"] = urlParams["q"].replace(/\[\[[^\[\]]+?\:\:(\+|%2B)\]\]/g, "");
	}
	
	//check initial correct facet checkboxes here
	for (f in facets) {
		checkbox = $('#po-'+f);
		if (checkbox != null) {
			checkbox.attr('checked', true);
		}
	}
});

function toggleFacet(name) {	
	var index = indexOf(name, facets);
	if (index == -1) {
		facets.push(name);
	} else {
		facets.splice(index, 1);
	}
	
	//todo: toggle all other occurences of this facet (i.e. for other categories)
	
	urlParams['po'] = "%3F"+facets.join("%0D%0A%3F")+"%0d%0A";
	for (var i=0; i<facets.length; i++) {
		urlParams['q'] += "[["+facets[i]+"::%2B]]";
	}
	
	var paramsString = '';
	for (param in urlParams) {
		paramsString += param+"="+urlParams[param]+"&";
	}
	//var paramsString = [name+"="+urlParams[param]+"&" for (param in urlParams)];
	window.location.href = "?"+paramsString;
	
}

function test() {
	sajax_do_call('ATWCategoryStore::ajaxGetFacets',
		["Tool"], 
		function (data) {
			alert(data);
		}
	);
}

function indexOf(needle, haystack) {
	var length = haystack.length;
    for(var i = 0; i < length; i++) {
        if(haystack[i] == needle) return i;
    }
    return -1;
}

// from http://jquery-howto.blogspot.com/2009/09/get-url-parameters-values-with-jquery.html comment
function getUrlParameters() {
	var map = {};
	var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
		map[key] = value;
	});
	return map; 
}
