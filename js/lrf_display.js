var lrf_origin_content_json = '{{content}}'.replace(/&quot;/ig, '"');
var lrf_origin_content = JSON.parse(lrf_origin_content_json);

var lrf_search_term = "{{search_term}}";
var lrf_searched_content = [];

var context_length = 5;

lrfSearch();

/**
 * Call to update the search
 * Gets new lrf_search_term and initiate the new search
 */
function lrfUpdateSearch() {
    lrf_search_term = document.getElementById("searchinput-{{uniqid}}").value;
    lrfSearch();
}

/**
 * searches the lrf_origin_content using the new lrf_search_term
 */
function lrfSearch() {
    lrf_searched_content = [];
    if (lrf_search_term) {
        lrf_origin_content.forEach(lrfSearchContent);
        lrfDisplayContent();
    } else {
        document.getElementById("lrf-search-term-{{uniqid}}").innerHTML = "{{search_term_label}}";
        document.getElementById("lrf-content-{{uniqid}}").innerHTML = '';
    }
}

/**
 * searches one pdf-page/book-chapter using the lrf_search_term and if found ads it with context to lrf_searched_content
 * @param {*} page 
 */
function lrfSearchContent(page) {
    var _content = ' ' + page.content.toLowerCase() + ' ';
    var _search = lrf_search_term.toLowerCase();

    // Is the searched word in this page?
    if (!(_content.includes(_search))) return;

    // Create a String with all occurences & context
    var context = '';

    var index = _content.indexOf(_search);
    var index_end = -1;

    while (0 <= index) {
        var i_start = index;
        var i_end = index;
        for (var i = 0; i < context_length; i++) {
            i_start = _content.lastIndexOf(' ', i_start - 1);
            i_end = _content.indexOf(' ', i_end + 1);
            if (i_end < i_start) {
                i_end = _content.length;
            }
        }
        if (i_start > index_end) {
            context += '...';
            context += page.content.substring(i_start, i_end);
        } else {
            context += page.content.substring(index_end + 1, i_end);
        }
        index_end = i_end;
        index = _content.indexOf(_search, index + 1);
    }
    context += '...';

    page.context = context;
    if (!(page.filename in lrf_searched_content)) {
        lrf_searched_content[page.filename] = [];
    }
    lrf_searched_content[page.filename][page.page] = page;
}

/**
 * displayes the lrf_searched_content in a list format
 */
function lrfDisplayContent() {
    document.getElementById("lrf-search-term-{{uniqid}}").innerHTML = "{{search_term_label}}" + lrf_search_term;

    var display = '';
    for (var pdf_name in lrf_searched_content) {
        display += '<h4>' + pdf_name + '</h4>';
        display += '<ul style="max-height: 200px; overflow: auto;">';
        for (var chapter in lrf_searched_content[pdf_name]) {
            display += '<li>' +
                '<a href="' + lrf_searched_content[pdf_name][chapter].book_url + '">' +
                '{{chapter_label}}' + '-' + chapter +
                '</a>: ' + lrf_searched_content[pdf_name][chapter].context +
                '</li>';
        }
        display += '</ul>';
    }
    document.getElementById("lrf-content-{{uniqid}}").innerHTML = display;
}