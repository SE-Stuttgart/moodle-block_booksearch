# Slide Finder #

## German description

**English version please see below**

Das Plugin wurde entwickelt, um ein Suchfeld für die textbasierte Suche nach Begriffen in den Lehrmaterialien bereitzustellen. 

Es kann an vielen Stellen in einem Kurs alternative Lehrmaterialien geben (siehe Beschreibung, Plugin: [Autocomplete Related Activities](https://github.com/SE-Stuttgart/kib3_moodleplugin_autocompleteactivities/blob/master/README.md)). Dazu gehören Links zu externen Videos ebenso wie bildbasierte Moodle-Bücher, die beide keine textbasierte Suche erlauben. Als Alternative gibt es jedoch immer ein PDF mit identischem Inhalt (PDF, Video und Buch werden aus derselben Quelle generiert). Das Plugin macht sich die Tatsache zunutze, dass die PDFs eine textbasierte Suche erlauben, und ermöglicht so eine textbasierte Suche nach Begriffen. Es bietet dann die Möglichkeit, direkt auf die Seite mit dem Treffer im Moodle-Buch zu springen.

## English description

The plugin is developed to provide a search field for text-based search for terms in the teaching materials. 

There can be alternative teaching materials in many places in a course (see above, Plugin: [Autocomplete Related Activities](https://github.com/SE-Stuttgart/kib3_moodleplugin_autocompleteactivities/blob/master/README.md)). These include links to external videos as well as image-based Moodle books, neither of which allow text-based searching. However, there is always a PDF as an alternative with identical content (PDF, video and book are generated from the same source). The plugin takes advantage of the fact that the PDFs allow text-based searching, and thus allows text-based searching for terms. It then provides the ability to jump directly to the page with the hit in the Moodle book.


# Usage and API configuration

### Type: Block
Add a block to either your dashboard or a course.

### Course Selection
If you are on your dashboard you will have to first select the course you want to search in (this will trigger a site reload) using a dropdown menu.

### Search
You can use the input field to search for a text snippet you want to see results for. The results will get updated automatically.

### Results
The results will be displayed in the block just below the search bar and will update automatically.
They are ordered under PDF/Book source and link to the respective book chapter.

## Webservice

Follow the instructions on _Site Administration > Server > Web services > Overview_ to register the web service and set the right for a specified user to use it.
This involves the following steps:
1. Enable web services
2. Enable protocols: Her, enable the REST protocol if not already enabled.
3. Create a specified user: This can be an user representing the chatbot.
4. Check user capability: The specified user has to have at least the __webservice/rest:use__ capability.
5. Select a service: Add the "Lecture Reference Finder" to custom services.
6. Add functions: Add the "block_lecture_reference_finder_get_searched_locations" function to the "Lecture Reference Finder" service.
7. Select a specified user: Add the web services user as an authorised user.
8. Create a token for a user: Create a token for the web services user.

Test it by sending an http GET request to
'http://[yourmoodle]/webservice/rest/server.php?wstoken=[user-token]&wsfunction=block_lecture_reference_finder_get_searched_locations&moodlewsrestformat=json&search_string=[search_string]&course_id=[course_id]&context_length=[context_length]'
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
