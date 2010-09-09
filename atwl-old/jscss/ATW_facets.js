var urlParams; // array of url parameters for rebuilding the url
var facets; // array of currently-selected facets

jQuery(document).ready(function() {
	jQuery("#facettable").hide();
	
	urlParams = getUrlParameters();
	//urlParams['p'] = 'weird';
	//urlParams['po'] = '';
	
	// setup events
	jQuery("#facetsbutton").click(function() {
		jQuery("#facettable").toggle();		
	});
	
	
	/*facets = {}; */ //moved to PHP
	
	// X -- todo: this isn't working for when params are passed via x
	
	// extract properties from printouts parameter
	/*
	if (urlParams["po"] != null) {
		
		// first get the facets with no labels
		// since a regex that handled both wasn't working and this is adequate
		urlParams["po"].replace(/%3F\s*(.+?)\s*%0D%0A/gi, function(m,facet) {
			if (!facet.search(/%3D/)) {
				f = facet.replace(' ', '_');
				facets[f] = f;
			}
		});
		
		// then get the facets with labels
		urlParams["po"].replace(/%3F\s*(.+?)(\+*%3D\+*(.+?))?\s*%0D%0A/gi, function(m,facet,group,label) {
			facets[facet.replace(' ', '_')] = label;
		});
	}*/
	
	// remove [[Property:+]] statements, which will be rebuilt
	//if (urlParams["q"] != null) {
		urlParams["q"] = queryString.replace(/\[\[[^\[\]]+?\:\:(\+|%2B)\]\]/gi, "");
		urlParams["q"] = urlParams["q"].replace(/%5B%5B[^%]+?%3A%3A%2B%5D%5D/gi, "");
	//} else {
	//	urlParams
		
	//}
	
	// check initial facets checkboxes
	for (var i in facets) {
		checkbox = jQuery('#po-'+i);
		if (checkbox != null) {
			checkbox.attr('checked', true);
		}
	}
	
	// todo: toggle all other checkbox occurences of this facet (i.e. for other categories)
	// slash switch to classes, not ids, for checkboxes
});

function toggleFacet(name) {
	// update facets object
	if (facets[name] != null) {
		delete facets[name];
	} else {
		facets[name] = name;
	}
	
	/*if (urlParams['po'] === undefined) {
		urlParams['po'] == '';
	}*/
	urlParams['po'] = '';	
	// rebuild query string and printouts parameters
	for (var i in facets) {
		if (printoutsMustExist) {
			urlParams['q'] += "[[" + i + "::%2B]]";
		}
		
		if (facets[i].replace('_', ' ') == i.replace('_', ' ') ) {
			//urlParams['po'] += "%3F" + i.replace('_', ' ') + "%0d%0A";
			urlParams['po'] += "?" + i.replace('_', ' ') + "%0d%0A";
		} else {
			//urlParams['po'] += "%3F" + i + "+%3D+" + facets[i].replace('_', ' ') + "%0d%0A";
			urlParams['po'] += "?" + i + "+%3D+" + facets[i].replace('_', ' ') + "%0d%0A";
		}		
	}

	
	if (wgUseAjax && false) {
		urlParams['atwajax'] = '1';
		//alert(urlParams['po']);
		jQuery.get('?', urlParams, function(data) {
			alert(data);
			jQuery("#bodyContent").html(data);
			
		});
		urlParams['atwajax'] = 0;
	} else {
		// rebuild page url
		var paramsString = '';
		for (param in urlParams) {
			paramsString += param+"="+urlParams[param]+"&";
		}
		
		window.location.href = "?"+paramsString;	
	}
}

// from http://jquery-howto.blogspot.com/2009/09/get-url-parameters-values-with-jquery.html comment
function getUrlParameters() {
	var map = {};
	var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
		map[key] = value;
	});
	return map; 
}

/*
function test() {
	sajax_do_call('ATWCategoryStore::ajaxGetFacets',
		["Tool"], 
		function (data) {
			alert(data);
		}
	);
}
*/

/*
function indexOf(needle, haystack) {
	var length = haystack.length;
    for(var i = 0; i < length; i++) {
        if(haystack[i] == needle) return i;
    }
    return -1;
}
*/
