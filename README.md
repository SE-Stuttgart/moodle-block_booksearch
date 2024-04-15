# Book Search #


## English Description

**German version please see below**

The plugin was developed to provide a search field for text search in Moodle books with image-based slides. The plugin requires that a PDF of (almost) the same name is present in the same course section. The pages of the PDF need to correspond 1-1 to the pages in the book. Names of book and PDF need to be identical, except for text specified in brackets inside their names.

The plugin searches all PDFs for the text and then displays the search results as a list of links. Clicking on a link shows the respective page with the corresponding number inside the book.

The search function is available if a corresponding block is configured (see below). Alternatively, the function may be reached using a web service. The configuration of the web service is also described below.

More details on functionality and configuration of this plugin can be found in the [Wiki](https://github.com/SE-Stuttgart/kib3_moodleplugin_booksearch/wiki).

## German Description

Das Plugin wurde entwickelt, um ein Suchfeld für die Textsuche nach Begriffen in Moodle-Büchern mit bildbasierten Folien bereitzustellen. Es erfordert, dass zu jedem Buch ein durchsuchbares PDF mit (fast) gleichen Namens bereitgestellt wird, dessen Seiten 1:1 zu den Seiten im Buch passen. Das PDF muss im selben Abschnitt wie das Buch liegen. Die Namen von Buch und PDF müssen identisch sein, ausgenommen ist in Klammern spezifizierter Text innerhalb der Namen.

Das Plugin sucht in allen PDFs nach den Begriffen und zeigt die Resultate als eine Liste von Links an. Durch Klicken auf den gewünschten Link wird die Seite mit der entsprechenden Seitennummer im Buch angezeigt.

Die Suchfunktion steht zur Verfügung, wenn man einen entsprechenden Block konfiguriert (s.u.). Alternativ kann sie über einen Webservice erreicht werden. Die Konfiguration des Web Service ist ebenfalls unten beschrieben.

Mehr Informationen zu den Funktionen und zur Konfiguration des Plugins sind im [Wiki](https://github.com/SE-Stuttgart/kib3_moodleplugin_booksearch/wiki) zu finden.


# Usage and API Configuration

### Type: Block
Add a block to either your dashboard or a course.
You can add a block by turning on "edit mode" and then pressing "add a block" on the right side of the user interface.
Then you can select "Book Search" to add the block.

### Course Selection
If you are on your dashboard, you will have to first select the course you want to search in (this will trigger a site reload) using a dropdown menu.

### Search
You can use the input field to search for a text snippet you want to see results for. The results will get updated automatically.

### Results
The results will be displayed in the block just below the search bar and will update automatically.
They are ordered under PDF/Book source and link to the respective book chapter.

## Web Service

To configure the web service, follow the instructions on _Site Administration > Server > Web services > Overview_ to register the web service and set the right for a specified user to use it.
This involves the following steps:
1. Enable web services.
2. Enable protocols: Here, enable the REST protocol if not already enabled.
3. Create a new specific user.
4. Check user capability: The specified user has to have at least the __webservice/rest:use__ capability. If the user does not have the permission, you can add the permission to a role and then add the user to that role.
5. Select a service: Add the "Book Search" to custom services.
6. Add functions: Add the "block_booksearch_get_searched_locations" function to the "Book Search" service.
7. Select a specific user: Add the web services user as an authorised user.
8. Create a token for a user: Create a token for the web services user.

Test it by sending an http GET request to
'http://[yourmoodle]/webservice/rest/server.php?wstoken=[user-token]&wsfunction=block_booksearch_get_searched_locations&moodlewsrestformat=json&search_string=[search_string]&course_id=[course_id]&context_length=[context_length]'
where
- yourmoodle: domain of your moodle installation (as developer: probably localhost)
- user-token: token received from moodle for a user which is allowed to use the web service
- search_string: the search string which is used to search in moodle books and pdfs
- course_id: the id of the course the string is searched in
- context_length: the number of word before and after each found string

You should get a response with your search results in the JSON format.


## Installing via Uploaded ZIP File ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing Manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/blocks/booksearch

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Third-party APIs ##
Book Search plug-in relies on [pdfparser](https://github.com/smalot/pdfparser) package to extract data from PDF files. Pdfparser is a standalone PHP package under LGPLv3 license (see above: [thirdpartylibs.xml](https://github.com/SE-Stuttgart/kib3_moodleplugin_booksearch/blob/main/thirdpartylibs.xml) for more details).

## License ##

2022 University of Stuttgart kasra.habib@iste.uni-stuttgart.de

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.

## Förderhinweis
**English version please see Acknowledgement below**

Diese Software wird im Rahmen des Projekts $KI$ $B^3$ -  *Künstliche Intelligenz in die Berufliche Bildung bringen* als InnoVeET-Projekt aus Mitteln des Bundesministeriums für Bildung und Forschung gefördert. Projektträger ist das Bundesinstitut für Berufsbildung (BIBB). Im Projekt werden eine Zusatzqualifikation (DQR 4) sowie zwei Fortbildungen (auf DQR5- bzw. DQR-6 Level) für KI und Maschinelles Lernen entwickelt. Die Software soll die Lehre in diesen Fortbildungen unterstützen.

## Acknowledgement
This software is developed in the project $KI$ $B^3$ -  *Künstliche Intelligenz in die Berufliche Bildung bringen*. The project is funded by the German Federal Ministry of Education and Research (BMBF) as part of the InnoVET funding line, with the Bundesinstitut für Berufsbildung (BIBB) as funding organization. The project also develops vocational training programs on Artificial Intelligence and Machine Learning (for DQR levels 4, 5, and 6). The software supports teaching in these programs. 

