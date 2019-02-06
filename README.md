# Github Org Reporting

The goal of this repo is to summarize github activity for your organization.

### Usage

`php script.php`

Will output a CSV of all github users and their contributions from `SINCE` till today.

### Config
`cp .env.example .env`
Fill in the required info

### Sample
```csv
name,additions,deletions,commits
a,0,0,0
b,10,0,1
c,268,16,5
```