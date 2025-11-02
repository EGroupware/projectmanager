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

The following schema is used for JSON encoding of projects:
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

> Attributes marked as `(readonly)` should never be sent, they are only received in `GET` requests!

(*) not every accountType allows setting this value!

(**) these values can either be set/overwritten in the project or are calculated from the project's elements

The response to the initial `POST` request to create a project contains a `Location` header to the newly created resource
`/projectmanager/<project-id>`, which can be used to further modify the course with a `PUT` or `PATCH` request,
read it's current state with a `GET` requests or `DELETE` to delete it.

### Member

The following schema is used for JSON encoding of a project-member:
* `@type`: `projectMember`
* `member`: string email, if set, or account-name
* `role`: string with the following stock roles (more can be defined)
    * `Coordinator`: full access
    * `Accounting`: edit access, incl. editing of budget and elements
    * `Assistent`: read access, incl. budget and adding elements
    * `Projectmember`: read access, no budget
* `roleDescription`: string, for stock roles see above
* `roleAcl`: integer
* `roleId`: integer
* `availability`: integer between 0 and 100
* `accountID`: integer


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
    "/projectmanager/1": {
        "@type": "project",
        "id": 1,
        "number": "P-2025-0001",
        "title": "Test Project",
        "creator": "ralf@boulder.egroupware.org",
        "created": "2025-01-01T12:00:00Z"
        "status": "active",
        "access": "public",
        ...
    },
    "/projectmanager/2": {
        "@type": "project",
        "id": 2,
        "number: "P-2025-0002"
        "title": "2nd project",
        "owner": "birgit@boulder.egroupware.org",
        ...
    },...
}
```
</details>

Following GET parameters are supported to customize the returned properties:
- `props[]=<DAV-prop-name>` e.g. `props[]=displayname` to return only the name (multiple DAV properties can be specified)
  Default for smallpart collections is to only return course-data (JsCourse)
- sync-token=<token> to only request change since last sync-token, like rfc6578 sync-collection REPORT
- nresults=N limit number of responses (only for sync-collection / given sync-token parameter!)
  this will return a "more-results"=true attribute and a new "sync-token" attribute to query for the next chunk

The GET parameter `filters` allows to filter or search for a pattern in the courses of a user:
- `filters[search]=<pattern>` searches for `<pattern>` in the courses like the search in the GUI
- `filters[status]=active` returns only active projects
- `filters[<attribute-name>]=<value>` filters by a DB-column name and value

<details>
   <summary>Example: Getting just (display-)name of all projects</summary>

```
curl -i 'https://example.org/egroupware/groupdav.php/projectmanager/?props[]=displayname' -H "Accept: application/pretty+json" --user <username>

{
  "responses": {
    "/projectmanager/1": "Testproject",
    "/projectmanager/2": "2nd project",
    ...
  }
}
```
</details>

#### **POST** requests to collection with a ```Content-Type: application/json``` header create a new project
> Location header in response gives URL of new course

<details>
   <summary>Example: POST request to create a new course</summary>

```
cat <<EOF | curl -i -X POST 'https://example.org/egroupware/groupdav.php/smallpart/' -d @- \
  -H 'Content-Type: application/json' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'
{
    "title": "New project",
}
EOF

HTTP/1.1 201 Created
Content-Type: application/json
Location: /egroupware/groupdav.php/smallpart/27

{
    "@type": "project",
    "id": 3,
    "number": "P-2025-0003",
    "name": "New project",
    "creator": "ralf@boulder.egroupware.org",
    "created": "2025-01-01T12:00:00Z"
    "status": "active",
    "access": "public",
    "etag": "3:12345678",
}
```
</details>

#### **PATCH** requests with a ```Content-Type: application/json``` header to change e.g. the project-title and status

<details>
   <summary>Example: PATCH request to update a project</summary>

```
cat <<EOF | curl -i -X PATCH 'https://example.org/egroupware/groupdav.php/projectmanager/1' -d @- \
  -H 'Content-Type: application/json' --user <username> \
  -H 'Accept: application/pretty+json' -H 'Prefer: return=representation'
{
    "title": "Test project (finished)",
    "status": "archived"
}
EOF

HTTP/1.1 200 OK
Content-Type: application/json

{
    "@type": "project",
    "id": 1,
    "number": "P-2025-0001",
    "title": "Test project (finished)",
    "status": "archived"
    ...
}
```
</details>