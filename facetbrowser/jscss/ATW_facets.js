var urlParams; // array of url parameters for rebuilding the url
//var facets; // array of currently-selected facets

function setupEvents() {
    // setup events
    jQuery("#atwQfacetsbutton").click(function () {
        jQuery("#atwQfacettable").toggle();
        var html = jQuery("#atwQfacetsbutton").html();
        if (html == 'Show facets') {
            jQuery("#atwQfacetsbutton").html("Hide facets");
        } else {
            jQuery("#atwQfacetsbutton").html("Show facets");
        }
    });
}

jQuery(document).ready(function () {
    setupEvents();

    jQuery("#atwQfacettable").hide();

    urlParams = getUrlParameters();
    stripParams();

    if (urlParams["showFacetsOnLoad"] == '1') { // for use with non-Ajax
        jQuery("#atwQfacettable").show();
        jQuery("#atwQfacetsbutton").html("Hide facets");
    }

});

function stripParams() {
    // remove [[Property:+]] statements, which will be rebuilt

    urlParams["q"] = queryString.replace(/\[\[[^\[\]]+?\:\:(\+|%2B)\]\]/gi, "");
    urlParams["q"] = urlParams["q"].replace(/%5B%5B[^%]+?%3A%3A%2B%5D%5D/gi, "");
    urlParams["po"] = '';
}


function toggleFacet(key) {
    for (var i in facets) {
        if (facets[i]['key'] == key) {
            facets[i]['checked'] = !facets[i]['checked'];
            break;
        }
    }

    updateTable();
}

function setFacetLabel(key, label) {
    for (var i in facets) {
        if (facets[i]['key'] == key) {
            facets[i]['label'] = label;
            break;
        }
    }
}

function updateTable() {
    // construct the q and po URL parameters
    urlParams['po'] = '';

    // Basil: remove parts for facets such as [[Name::+]].
    var val = urlParams['q'];
    val = val.replace(/\[\[[^\[\]]*::\+\]\]/g, "");
    urlParams['q'] = val;

    for (var i in facets) {
        if (facets[i]['checked']) {
            if (printoutsMustExist) {
                urlParams['q'] += "[[" + facets[i]['name'] + "::+]]";
                //alert("add [[...::+]]" + facets[i]['name']);
            }

            if (facets[i]['label'] == null || facets[i]['label'] == facets[i]['name']) {
                urlParams['po'] += "?" + facets[i]['name'] + "\n";
            } else {
                urlParams['po'] += "?" + facets[i]['name'] + "+=+" + facets[i]['label'] + "\n";
            }
        }
    }

    // make sure the facets table doesn't get hidden upon reload
    urlParams['showFacetsOnLoad'] = 1;

    var paramsString = '';
    for (p in urlParams) {
        paramsString += p + "=" + urlParams[p] + "&";
    }

    if (wgUseAjax) {
        urlParams['SFBAjax'] = 1;
        jQuery.get('?', urlParams, function (data) {
            // a little ugly, but works
            var facetbox = jQuery("#atwQfacetbox");
            jQuery("#bodyContent").html(data);
            facetbox.appendTo("#bodyContent");
            //setupEvents();
            window.location.href = "#";  // somewhat makes browser history buttons work
            $('.smwtable').tablesorter();
        });
    } else {
        var paramsString = '';
        for (p in urlParams) {
            paramsString += p + "=" + urlParams[p] + "&";
        }

        window.location.href = '?' + paramsString;
    }
}

/*
function toggleFacet(name) {
	// update facets object
	if (facets[name] != null) {
		delete facets[name];
	} else {
		facets[name] = name;
	}
	
	if (urlParams['po'] === undefined) {
		urlParams['po'] == '';
	}
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
*/

// not in use
function checkInitialFacets() {
    for (var i in facets) {
        if (facets[i]['checked']) {
            checkbox = jQuery('#po-' + facets[i]['key']);
            if (checkbox != null) {
                checkbox.attr('checked', true);
            } else {
                alert("Error");
            }
        }

    }
}

// from http://jquery-howto.blogspot.com/2009/09/get-url-parameters-values-with-jquery.html comment
function getUrlParameters() {
    var map = {};
    var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
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
