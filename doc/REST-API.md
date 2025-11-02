# EGroupware REST API for ProjectManager

Authentication is via Basic Auth with username and a password, or a token valid for:
- either just the given user or all users
- CalDAV/CardDAV Sync (REST API)
- ProjectManager application

All URLs used in this document are relative to EGroupware's REST API URL:
`https://egw.example.org/egroupware/groupdav.php/`

That means instead of `/projectmanager/` you have to use the full URL `https://egw.example.org/egroupware/groupdav.php/projectmanager/` replacing `https://egw.example.org/egroupware/` with whatever URL your EGroupware installation uses.

### Hierarchy

`/projectmanager`  application collection (`POST` request to create a new project)
+ `/<project-id>` project object (`GET`, `PATCH`, `PUT` or `DELETE`, `POST` to create a new project)
    + `/members` project-members objects, _not yet implemented_
        + `/<account-id>` object of single member (`PUT` to set role or nickname, `DELETE` to unsubscribe)
    + `/elements` collection of available project-elements, _not yet implemented_

### State of the REST API implementation
- [x] create, read, update or delete projects
- [x] list members incl. role and availability information
- [ ] add, update or delete members via the members collection
- [ ] pricelist: create, read, update and delete
- [ ] milestones: create, read, update and delete
- [ ] elements: create, read, update and delete
> All not (yet) implemented REST API features are, of course, available in the ProjectManager user-interface in EGroupware.

### Project

Projects are created via a `POST` request to the ProjectManager collection: `/projectmanager/`

Every course is a sub-collection in the above collection named by its ID.
With sufficient privileges courses can be edited with `PUT` or `PATCH` requests
and be closed/removed with `DELETE` requests.

The following schema is used for JSON encoding of courses
* `@type`: `project`
* `id`: integer (readonly) ID
* `number`: string project-number (must be unique!)
* `title`: string
* `description`: string (multiple lines, html)
* `creator`, `modifier`: string (readonly) email, if set, or account-name
* `created`, `modified`: string DateTime in UTC (readonly)
* `plannedStart`, `realStart`: string DateTime in UTC, planned or real start of the project (**)
* `plannedEnd`, `realEnd`: string DateTime in UTC, planned or real end of the project (**)
* `plannedTime`, `usedTime`, `replannedTime`: int in seconds, (budgeted) time of the project (*) (**)
* `plannedBudget`, `usedBudget`: string (two digit decimal value), used or planned budget (*) (**)
* `category`: object with single (!) category-name and value of true
* `access`: string, either "public" or "private"
* `priority`: integer value between 1 (=lowest) and 9 (=highest)
* `status`: string, either 
  * `active` default for new created projects
  * `nonactive` de-activated project
  * `archive` archived project 
  * `template` template project, can be used to create new projects incl. elements
  * `deleted` deleted project, only if keeping of deleted projects is enabled in configuration
* `completion`: integer value between 0 and 100 in percent (**)
* `overwrite`: array (readonly) of attribute-names, which are overwritten by this project (not calculated by its elements)
* `accountingType`: string, either `status`, `times`, `budget` or "pricelist"
* `members`: object (readonly) with accountID JsProjectMember object pairs, the members of the project and their roles
* `egroupware.org:customfields`: custom-fields object, see other types
* `etag`: string `"<id>:<modified-timestamp>"` (double quotes are part of the etag!)
  
(*) not every accountType allows setting this value!
(**) these values can either be set/overwritten in the project or are calculated from the project's elements

* `org`: string account-name of group to limit visibility of course to it's members
* `closed`: bool flag if course is closed, default false
* `options`: object with following boolean attributes
    * `recordWatched`: boolean, record start-, end-time and position of watched videos
    * `videoWatermark`: boolean, show a watermark on all videos
    * `cognitiveLoadMeasurement`: boolean, true to enable CognitiveLoadMeasurement
    * `allowNeutralLFcategories`: boolean
* `participants`: (readonly) object of participant-objects indexed by their numerical account-ID
* `materials`: (readonly) object with ID and name pairs of the existing materials / course-parts

The response to the initial `POST` request to create a course contains a `Location` header to the newly created resource
`/smallpart/<course-id>`, which can be used to further modify the course with a `PUT` or `PATCH` request,
read it's current state with a `GET` requests or use `DELETE` to close it.

### Participants

The following schema is used for JSON encoding of participants:
* `@type`: `participant`
* `account`: string (only `POST` request) email, user-name or int account-ID to create arbitrary participants
* `password`: string (only `POST` request) to subscribe to courses with a course access code
* `role`: `admin`, `teacher`, `tutor` or `student` (can only be set by `admin` role, otherwise readonly!)
* `alias`: name to show for students to other students (not used for staff members!)
* `name`: string (readonly) full-name, only available to staff (non-student roles)
* `group`: int (readonly) ID if students are in subgroups
* `subscribed`: DateTime-object (readonly) timestamp when student was subscribed
* `unsubscribed`: DateTime-object (readonly) timestamp when student was unsubscribed

Used requests to create or modify participants:
* `POST` requests to `/smallpart/<course-id>/participants/` are used to subscribe participants:
    * an empty body to subscribe the current user as new regular student
    * an object with the above (non-readonly) attributes, setting more than the `alias` requires a course-admin!
* Participants can change their `alias` via a `PATCH` request with an object with just the `alias` attribute containing the new alias.
* Course-admins can use `PUT` or `PATCH` requests to grant a higher role to participants or change the other attributes.
* Participants are unsubscribed via a `DELETE` request to `/smallpart/<course-id>/participants/<account-id>`.
> `DELETE` requests never remove the former participant, but just set the `unsubscribed` attribute to the current time.

### Materials or course-parts
Each course-collection `/smallpart/<course-id>/` containing course-parts as sub-collections, with a main document for the students to work on. The main document is either
* a video (mp4 or WebM) or
* a PDF document

Materials are created by sending a `POST` request to the course collection with either:
* a video (Content-Type: `video/(mp4|webm)`) or
* a PDF document (Content-Type: `application/pdf`) or
* a mp3 audio (Content-Type: `audio/mpeg`) or
* a JSON document (Content-Type: `application/json`) with metadata / object with the following attributes:
    * `@type`: `material`
    * `id`: integer (readonly) ID
    * `course`: integer (readonly) ID of course
    * `name`: string name of video
    * `date`: DateTime-object (readonly) last updated timestamp
    * `question`: string (multiple lines)
    * `hash`: string (readonly), used to construct video-urls
    * `url`: string URL of the mail-document, which can also be an external video e.g. on YouTube
    * `type`: string (readonly) either `mp4`, `webm`, `youtube`, `mpeg` (mp3 audio) or `pdf`, type of main document
    * `commentType`: string, one of:
        * `show-all` show all comments
        * `show-group` show comments of own group incl. teachers
        * `hide-other-students` hide comment of other students
        * `hide-teachers` hide comments of teachers/staff
        * `show-group-hide-teachers` show comment of own group, but hide teachers
        * `show-own` show students only their own comments
        * `forbid-students` forbid students to comment
        * `disabled` disable comments, e.g. for tests/exams
    * `published`: string of either:
        * `draft`: Only available to course admins
        * `published`: Available to participants during optional begin- and end-date and -time
        * `unavailable`: Only available to course admins, e.g. during scoring of tests
        * `readonly`: Available, but no changes allowed e.g. to let students view their test scores
    * `publishedStart`: optional DateTime string with start-time for above state `published`, e.g. "2024-06-01T09:00:00"
    * `publishedEnd`: optional DateTime string with end-time for above state `published`
    * `timezone`: name of timezone of above times e.g. "Europe/Berlin", defaults to user's timezone
    * `testDisplay` one of `instead-comments`, `dialog`, `video-overlay` or `list`
    * `testOptions`: object with following boolean attributes:
        * `allowPause` allow student to pause test
        * `forbidSeek` forbid student to seek in test
    * `testDuration`: integer optional duration in seconds, if this material/course-part is a test/exam
    * `attachments`: object (readonly) with filename and object pairs, with following attributes each:
        * `name`: string filename (also used as attribute-name of the object)
        * `url`: string URL of the file to download or update
        * `contentType`: string mime-type of the file
        * `size`: int, size of the file
    * `limitAccess`: object `<account>`: true, if given, limits access to published material to given students
      (staff always has access), e.g. `{"student@example.org": true, "student2@other.tld": true}`

> Attributes marked as `(readonly)` should never be sent, they are only received in `GET` requests!

The response contains a `Location` header with the newly created material collection `/smallpart/<course-id>/<material-id>/`.

The main document and the JSON meta-data can always be updated by sending a `PUT` request with appropriate `Content-Type` header.

A material or its JSON meta-data can be read via a `GET` request with correct `Accept`-header to distinguish between JSON meta-data and the main document.
> The server might respond with a redirect / `Location`-header to the `GET` request for the main document, instead of directly sending it!

A material / course-part is removed with a `DELETE` request to its collection URL.

Additional documents can be attached to a material / course-part and are displayed together with its question text by
sending a `POST` request to the materials `attachments` sub-collection: `/smallpart/<course-id>/<material-id>/attachments/`.
Attachments can be listed with a `GET` request to the `attachments` collection and updated or removed with `PUT` or `DELETE` requests to their URL.
> The server might respond to `GET` requests to an attachment-URL with a redirect / `Location` header!

### Supported request methods and examples

> Most examples use optional `Prefer: return=representation` and `Accept: application/pretty+json` headers,
> returning the complete objects to allow you to follow the changes made.

> `GET` requests require only an `Accept: application/json` header, `POST`, `PATCH` or `PUT` requests
> a `Content-Type: application/json` header.

#### **GET** to collections with an ```Accept: application/json``` header return all courses (the user has access to)
<details>
  <summary>Example: Getting all courses a given user has access to, could or already has subscribed</summary>

```
curl https://example.org/egroupware/groupdav.php/smallpart/ -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/smallpart/1": {
        "@type": "course",
        "id": 1,
        "name": "Christophs Testkurs",
        "org": "Default",
        "subscribed": true
    },
    "/smallpart/4": {
        "@type": "course",
        "id": 4,
        "name": "Test multiple import",
        "owner": "birgit@boulder.egroupware.org",
        "org": "Default"
    },...
}
```
</details>

Following GET parameters are supported to customize the returned properties:
- `props[]=<DAV-prop-name>` e.g. `props[]=displayname` to return only the name (multiple DAV properties can be specified)
  Default for smallpart collections is to only return course-data (JsCourse)
- ~~sync-token=<token> to only request change since last sync-token, like rfc6578 sync-collection REPORT~~ (not yet supported)
- ~~nresults=N limit number of responses (only for sync-collection / given sync-token parameter!)
  this will return a "more-results"=true attribute and a new "sync-token" attribute to query for the next chunk~~

The GET parameter `filters` allows to filter or search for a pattern in the courses of a user:
- `filters[search]=<pattern>` searches for `<pattern>` in the courses like the search in the GUI
- `filters[subscribed]=1` returns only subscribed courses
- `filters[<attribute-name>]=<value>` filters by a DB-column name and value

<details>
   <summary>Example: Getting just (display-)name of all courses</summary>

```
curl -i 'https://example.org/egroupware/groupdav.php/<username>/smallpart/?props[]=displayname' -H "Accept: application/pretty+json" --user <username>

{
  "responses": {
    "/smallpart/1": "Christophs Testkurs",
    "/smallpart/4": "Test multiple import",
    "/smallpart/6": "Ralf's Test Kurs",
    "/smallpart/7": "Test",
    "/smallpart/9": "Test Arash (Import)",
    "/smallpart/10": "Bug-Test-2023 Fragen",
    "/smallpart/11": "Test Cats",
    "/smallpart/12": "Test Cats Ralf",
    "/smallpart/13": "Test 3 Cats",
    "/smallpart/14": "Test 4. Cats",
    "/smallpart/15": "Test 5. Cats",
    "/smallpart/16": "Test 6. Cats"
  }
}
```
</details>

#### **GET**  requests with an ```Accept: application/pretty+json``` header can be used to retrieve single course / JsCourse schema

<details>
   <summary>Example: GET request for a single course showcasing available fields</summary>

```
curl 'https://example.org/egroupware/groupdav.php/smallpart/1' -H "Accept: application/pretty+json" --user <username>
{
    "@type": "course",
    "id": 1,
    "name": "Christophs Testkurs",
    "org": "Default",
    "options": {
        "recordWatched": false,
        "videoWatermark": false,
        "cognitiveLoadMeasurement": false,
        "allowNeutralLFcategories": false
    },
    "participants": {
        "202": {
            "@type": "participant",
            "account": "admin@egroupware.org",
            "alias": "Admin",
            "name": "Admin User",
            "role": "admin",
            "subscribed": "2021-08-27T16:14:41Z",
            "unsubscribed": "2021-09-27T13:21:13Z"
        },
        "44": {
            "@type": "participant",
            "account": "birgit@boulder.egroupware.org",
            "name": "Birgit Becker",
            "role": "admin",
            "subscribed": "2021-08-27T16:14:41Z"
        },
        "5": {
            "@type": "participant",
            "account": "ralf@boulder.egroupware.org",
            "name": "Ralf Becker",
            "role": "admin",
            "subscribed": "2022-12-07T16:01:25Z"
        },
        "374": {
            "@type": "participant",
            "account": "student@egroupware.org",
            "alias": "@Last",
            "name": "First Student",
            "role": "student"
            "group": "1",
            "subscribed": "2021-09-07T06:31:02Z"
        },
        "375": {
            "@type": "participant",
            "account": "second.student@egroupware.org",
            "name": "Second Student",
            "role": "student",
            "group": "1",
            "subscribed": "2021-09-07T06:31:02Z"
        }
     },
    "materials": {
        "1": "Brain Slices",
        "39": "CL Measurement",
        "77": "CTI Integration",
        "55": "DNA-Replicaion",
        "61": "EGw Tutorial",
        "3": "Stefan: LibreOffice Online"
    }
}
```
</details>

#### **POST** requests to collection with a ```Content-Type: application/json``` header add a new course
> Location header in response gives URL of new course

<details>
   <summary>Example: POST request to create a new course</summary>

```
cat <<EOF | curl -i -X POST 'https://example.org/egroupware/groupdav.php/smallpart/' -d @- \
  -H 'Content-Type: application/json' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'
{
    "name": "Ralf's REST API Course",
    "org": "Default"
}
EOF

HTTP/1.1 201 Created
Content-Type: application/json
Location: /egroupware/groupdav.php/smallpart/27

{
    "@type": "course",
    "id": 27,
    "name": "Ralf's REST API Course",
    "org": "Default",
    "options": {
        "recordWatched": false,
        "videoWatermark": false,
        "cognitiveLoadMeasurement": false,
        "allowNeutralLFcategories": false
    },
    "participants": {
        "5": {
            "id": 5,
            "name": "Ralf Becker",
            "role": "admin",
            "subscribed": "2024-05-14T05:08:13Z"
        }
    }
}
```
</details>

#### **POST** requests to course-collection to add a new PDF document / material with a ```Content-Type: application/pdf``` header
> Location header in response gives URL of new material / course-part

> Please note: curl requires `--data-binary` when uploading binary content like videos of PDF documents!

<details>
   <summary>Example: POST request to create a new PDF material</summary>

```
curl -i -X POST 'https://example.org/egroupware/groupdav.php/smallpart/27/' --data-binary @/path/to/test.pdf \
  -H 'Content-Type: application/pdf' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'

HTTP/1.1 201 Created
Content-Type: application/json
Location: /egroupware/groupdav.php/smallpart/27/120

{
    "@type": "material",
    "id": 120,
    "course": 27,
    "name": "No name",
    "date": "2024-05-14T07:17:19Z",
    "hash": "Y7jteFmHGiGaRVfVG9GQFhRLxQ7VIElziMAIXpIseXJbG5Yacu0B1fzOSTCLzSfR",
    "url": "https://example.org/egroupware/smallpart/Resources/Videos/Video/27/Y7jteFmHGiGaRVfVG9GQFhRLxQ7VIElziMAIXpIseXJbG5Yacu0B1fzOSTCLzSfR.pdf",
    "type": "pdf",
    "commentType": "show-all",
    "published": "published",
    "testOptions": {
        "allowPause": true,
        "forbidSeek": true
    },
    "testDisplay": "instead-comments"
}
```
</details>

#### **PATCH** requests with a ```Content-Type: application/json``` header to change e.g. the material-name and published state

<details>
   <summary>Example: PATCH request to update a material / course-part</summary>

```
cat <<EOF | curl -i -X PATCH 'https://example.org/egroupware/groupdav.php/smallpart/27/120' -d @- \
  -H 'Content-Type: application/json' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'
{
    "name": "PDF document analysis",
    "published": "draft"
}
EOF

HTTP/1.1 200 OK
Content-Type: application/json

{
    "@type": "material",
    "id": 120,
    "course": 27,
    "name": "PDF document analysis",
    "date": "2024-05-14T07:17:19Z",
    "hash": "Y7jteFmHGiGaRVfVG9GQFhRLxQ7VIElziMAIXpIseXJbG5Yacu0B1fzOSTCLzSfR",
    "url": "https://example.org/egroupware/smallpart/Resources/Videos/Video/27/Y7jteFmHGiGaRVfVG9GQFhRLxQ7VIElziMAIXpIseXJbG5Yacu0B1fzOSTCLzSfR.pdf",
    "type": "pdf",
    "commentType": "show-all",
    "published": "draft",
    "testOptions": {
        "allowPause": false,
        "forbidSeek": false
    },
    "testDisplay": "instead-comments"
}
```
</details>

#### **PUT**  requests with a ```Content-Type: application/pdf``` header update the PDF document of the material

<details>
   <summary>Example: PUT request to update a materials main document (video or PDF)</summary>

```
curl -i -X PUT 'https://example.org/egroupware/groupdav.php/smallpart/27/120' --data-binary @/path/to/updated-test.pdf \
  -H 'Content-Type: application/pdf' --user <username>

HTTP/1.1 204 No Content
```
</details>

#### **PATCH**  requests with  a ```Content-Type: application/json``` header to publish the course-part

<details>
   <summary>Example: PATCH request to publish a course-part</summary>

```
cat <<EOF | curl -i -X PATCH 'https://example.org/egroupware/groupdav.php/smallpart/27/120' -d @- \
  -H 'Content-Type: application/json' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'
{
    "question": "Mark parts of the PDF document you strongly disagree and why",
    "published": "published",
    "publishedStart": "2024-06-01T09:00:00",
    "publishedEnd": "2024-07-01T00:00:00"
}
EOF

HTTP/1.1 200 OK
Content-Type: application/json

{
    "@type": "material",
    "id": 120,
    "course": 27,
    "name": "PDF document analysis",
    "date": "2024-05-14T07:17:19Z",
    "question": "Mark parts of the PDF document you strongly disagree and why",
    "hash": "Y7jteFmHGiGaRVfVG9GQFhRLxQ7VIElziMAIXpIseXJbG5Yacu0B1fzOSTCLzSfR",
    "url": "https://example.org/egroupware/smallpart/Resources/Videos/Video/27/Y7jteFmHGiGaRVfVG9GQFhRLxQ7VIElziMAIXpIseXJbG5Yacu0B1fzOSTCLzSfR.pdf",
    "type": "pdf",
    "commentType": "show-all",
    "published": "published",
    "publishedStart": "2024-06-01T09:00:00",
    "publishedEnd": "2024-07-01T00:00:00",
    "timezone": "Europe/Berlin",
    "testOptions": {
        "allowPause": true,
        "forbidSeek": true
    },
    "testDisplay": "instead-comments"
}
```
</details>

#### **POST** requests to course-collection to add a YouTube video as material with a ```Content-Type: application/json``` header
> Location header in response gives URL of new material / course-part

<details>
   <summary>Example: POST request to create a new PDF material</summary>

```
CAT <<EOF | curl -i -X POST 'https://example.org/egroupware/groupdav.php/smallpart/27/' -d @- \
  -H 'Content-Type: application/json' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'
{
    "name": "Found on YouTube",
    "url": "https://www.youtube.com/watch?v=6swKzunmUHA",
    "question": "Lots of interesting information, please watch and learn ;)",
    "published": "draft"
}
EOF

HTTP/1.1 201 Created
Content-Type: application/json
Location: /egroupware/groupdav.php/smallpart/27/121

{
    "@type": "material",
    "id": 121,
    "course": 27,
    "name": "Found on YouTube",
    "date": "2024-05-14T08:57:01Z",
    "question": "Lots of interesting information, please watch and learn ;)",
    "url": "https://www.youtube.com/watch?v=6swKzunmUHA",
    "type": "youtube",
    "commentType": "show-all",
    "published": "draft",
    "testOptions": {
        "allowPause": true,
        "forbidSeek": true
    },
    "testDisplay": "instead-comments"
}
```
</details>

#### **PUT** requests to add a new or update an existing attachment of a material / course-part

> You have to follow the redirect send by EGroupware / use e.g. curl's `-L` of `--follow` parameter!

> curl requires to use `--data-binary` to upload binary data like PDF documents, videos or images!

<details>
   <summary>Example: PUT request to add or update an attachment</summary>

```
curl -iL -X PUT https://example.org/egroupware/groupdav.php/smallpart/27/121/attachments/Anleitung \
  -H 'Content-Type: application/pdf' --data-binary @/path/to/Anleitung.pdf --user <user>

HTTP/1.1 307 Temporary Redirect
Location: /egroupware/webdav.php/apps/smallpart/27/121/all/task/Anleitung.pdf

HTTP/1.1 201 Created
```
</details>

#### **DELETE** request to close an existing course
> You need to role `admin` to be able to close a course!

<details>
   <summary>Example: DELETE request to close a course</summary>

```
curl -iL -X PUT https://example.org/egroupware/groupdav.php/smallpart/27 --user <user>

HTTP/1.1 204 No Content
```
</details>

#### **DELETE** request to remove existing material / course-parts

> Please note: materials / course parts can only be deleted, if it has no comments or answers!

<details>
   <summary>Example: DELETE request to remove material / course-parts</summary>

```
curl -iL -X DELETE https://example.org/egroupware/groupdav.php/smallpart/27/121 --user <user>

HTTP/1.1 307 Temporary Redirect
Location: /egroupware/webdav.php/apps/smallpart/27/121/all/task/Anleitung.pdf

HTTP/1.1 204 No Content
```
</details>

#### **DELETE** requests to remove an existing attachment of a material / course-part

> You have to follow the redirect send by EGroupware / use e.g. curl's `-L` of `--follow` parameter!

<details>
   <summary>Example: DELETE request to add or update an attachment</summary>

```
curl -iL -X PUT https://example.org/egroupware/groupdav.php/smallpart/27/121/attachments/Anleitung.pdf --user <user>

HTTP/1.1 307 Temporary Redirect
Location: /egroupware/webdav.php/apps/smallpart/27/121/all/task/Anleitung.pdf

HTTP/1.1 204 No Content
```
</details>

#### **POST** requests to subscribe a participant or **PUT** request to set role and nickname

> Only role `admin` or `teacher` can subscribe arbitrary participants, everyone else can only subscribe themselves.

<details>
   <summary>Example: POST request to subscribe the current user without a course access code</summary>

```
curl -iL -X POST https://example.org/egroupware/groupdav.php/smallpart/27/participants/ \
  --user <user> -d ''

HTTP/1.1 201 Created
```
</details>

<details>
   <summary>Example: POST request to subscribe the current user with a course access code</summary>

```
curl -iL -X POST https://example.org/egroupware/groupdav.php/smallpart/27/participants/ \
  --user <user> -H 'Content-Type: application/json' -d '{"password": "secret123"}'

HTTP/1.1 201 Created
```
</details>

<details>
   <summary>Example: PUT request to set a nickname for the current user (only allowed for students!)</summary>

```
curl -iL -X PUT https://example.org/egroupware/groupdav.php/smallpart/27/participants/123 \
  --user <user> -H 'Content-Type: application/json' -d '{"alias": "Student @Home"}'

HTTP/1.1 200 Ok
```
</details>


<details>
   <summary>Example: POST request to subscribe an arbitrary user as teacher (requires a course-admin!)</summary>

```
curl -iL -X POST https://example.org/egroupware/groupdav.php/smallpart/27/participants/ \
  --user <user> -H 'Content-Type: application/json' -d '{"account": "someone@some.org", "role": "teacher"}'

HTTP/1.1 201 Created
```
</details>

#### **DELETE** requests to unsubscribe a participant

> Only role `admin` or `teacher` can unsubscribe arbitrary participants, everyone else can only unsubscribe themselves.

<details>
   <summary>Example: DELETE request to unsubscribe a participant</summary>

```
curl -iL -X PUT https://example.org/egroupware/groupdav.php/smallpart/27/participants/123 --user <user>

HTTP/1.1 204 No Content
```
</details>

#### **POST** request to subscribe a participant / or current user to a course