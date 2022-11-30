# Slide Finder #

## German description

**English version please see below**

Das Plugin wurde entwickelt, um ein Suchfeld für die Textsuche nach Begriffen in Moodle-Büchern mit bildbasierten Folien bereitzustellen. Es erfordert, dass zu jedem Buch ein durchsuchbares PDF (fast) gleichen Namens bereit gestellt wird, dessen Seiten 1:1 zu den Seiten im Buch passen. Das PDF muss im selben Abschnitt wie das Buch liegen. Die Namen von Buch und PDF müssen identisch sein, ausgenommen ist in Klammern spezifizierter Text innerhalb der Namen.

Das Plugin sucht in allen PDFs nach den Begriffen und zeigt die Resultate als eine Liste von Links an. Durch Klicken auf den gewünschten Link wird die Seite mit der entsprechenden Seitennummer im Buch angezeigt.

Die Suchfunktion steht zur Verfügung, wenn man einen entsprechenden Block konfiguriert (s.u.). Alternativ kann sie über einen Webservice erreicht werden. Die Konfiguration des Web Service ist ebenfalls unten beschrieben.

## English description

The plugin was developed to provide a search field for text search in Moodle books with image-based slides. The plugin requires that a PDF of (almost) the same name is present in the same course section. The pages of the PDF need to correspond 1-1 to the pages in the book. Names of book and PDF need to be identical, except for text specified in brackets inside their names.

 The plugin searches all PDFs for the text and then displays the search results as a list of links. Clicking on a link shows the respective page with the corresponding number inside the book.


# Usage and API configuration

### Type: Block
Add a block to either your dashboard or a course.

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
1. Enable web services
2. Enable protocols: Her, enable the REST protocol if not already enabled.
3. Create a specified user
4. Check user capability: The specified user has to have at least the __webservice/rest:use__ capability.
5. Select a service: Add the "Slide Finder" to custom services.
6. Add functions: Add the "block_slidefinder_get_searched_locations" function to the "Slide Finder" service.
7. Select a specified user: Add the web services user as an authorised user.
8. Create a token for a user: Create a token for the web services user.

Test it by sending an http GET request to
'http://[yourmoodle]/webservice/rest/server.php?wstoken=[user-token]&wsfunction=block_slidefinder_get_searched_locations&moodlewsrestformat=json&search_string=[search_string]&course_id=[course_id]&context_length=[context_length]'
where
- yourmoodle: domain of your moodle installation (as developer: probably localhost)
- user-token: token received from moodle for a user which is allowed to use the web service
- search_string: the search string which is used to search in moodle books and pdfs
- course_id: the id of the course the string is searched in
- context_length: the number of word before and after each found string



## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/blocks/slidefinder

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Third-party APIs ##
Slide Finder plug-in relies on [pdfparser](https://github.com/smalot/pdfparser) package to extract data from PDF files. Pdfparser is a standalone PHP package under LGPLv3 license (see above: [thirdpartylibs.xml](https://github.com/SE-Stuttgart/kib3_moodleplugin_slidefinder/blob/main/thirdpartylibs.xml) for more details).

## License ##

2022 Universtity of Stuttgart kasra.habib@iste.uni-stuttgart.de

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
