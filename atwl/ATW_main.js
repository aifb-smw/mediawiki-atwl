function update() {
	sajax_do_call('SpecialATWL::ajaxGetResultOutput', 
		[passed, format, facets.join(",")], 
		document.getElementById('result'));
}

function changeFormat(element) {
	format = element.options[element.selectedIndex].value;
	update();
}

function toggleFacet(element) {	
	var property = element.id.substr(4);
	
	if (element.checked) {
		if (!inArray(property, facets)) {
			facets.push(property);
		}
	} else {
		index = indexOf(property, facets);
		if (index != -1) {
			facets.splice(index, 1);
		}
	}	
	update();
}

function indexOf(needle, haystack) {
	var length = haystack.length;
    for(var i = 0; i < length; i++) {
        if(haystack[i] == needle) return i;
    }
    return -1;
}

//from jQuery
function inArray(needle, haystack) {
    var length = haystack.length;
    for(var i = 0; i < length; i++) {
        if(haystack[i] == needle) return true;
    }
    return false;
}
