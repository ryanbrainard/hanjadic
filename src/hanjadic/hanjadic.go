package main

import (
	"strings"
	"html/template"
	"io"
	"flag"
	"net/http"
	"log"
	"database/sql"
	_ "github.com/mattn/go-sqlite3"
)

var db *sql.DB
var port *string

func init() {
	var err error
	db, err = sql.Open("sqlite3", "../hanja-dictionary/hanjadic.sqlite")
	die(err)
	port = flag.String("port", ":8089", "port")
}

type Hanja struct {
	Hanja      string
	Definition string
}

type SimilarWord struct {
	Hanja   string
	Hangul  string
	English string
}

type Results struct {
	Terms        string
	Hanjas       []Hanja
	Radicals     []string
	SimilarWords []SimilarWord
}

var templates = template.Must(template.ParseFiles("index.html"))

func die(err error) {
	if err != nil {
		panic(err)
	}
}

func search(terms string) Results {
	var results Results
	results.Terms = terms
	query := "*" + strings.Join(strings.Split(terms, " "), "*") + "*"
	stmt, err := db.Prepare("select hanja, hangul, english from (select hanja, hangul, english from hanjas where hidden_index match ? union select hanja, hangul, english from hanjas where english match ? union select hanja, hangul, english from hanjas where hangul match ? union select hanja, hangul, english from hanjas where hanjas match ?) order by hangul")
	die(err)
	defer stmt.Close()
	rows, err := stmt.Query(query, query, query, query)
	die(err)
	defer rows.Close()
	for rows.Next() {
		var hanja, hangul, english string
		rows.Scan(&hanja, &hangul, &english)
		results.SimilarWords = append(results.SimilarWords, SimilarWord{hanja, hangul, english})
	}
	for _, character := range terms {
		stmt, err := db.Prepare("select hanjas, definition from hanja_definition where hanjas = ?")
		die(err)
		defer stmt.Close()
		rows, err := stmt.Query(string(character))
		die(err)
		defer rows.Close()
		for rows.Next() {
			var hanja, definition string
			rows.Scan(&hanja, &definition)
			results.Hanjas = append(results.Hanjas, Hanja{Definition: definition, Hanja: hanja})
		}
	}
	rstmt, err := db.Prepare("select radical from radicals where hanjas match ?")
	die(err)
	defer rstmt.Close()
	rrows, err := rstmt.Query(terms)
	die(err)
	defer rrows.Close()
	for rrows.Next() {
		var radical string
		rrows.Scan(&radical)
		results.Radicals = append(results.Radicals, radical)
	}
	return results
}

func render(terms string, w io.Writer) {
	templates.ExecuteTemplate(w, "index.html", search(terms))
}

func handler(w http.ResponseWriter, req *http.Request) {
	if req.Method == "POST" {
		http.Redirect(w, req, "/" + req.FormValue("q"), 302)
		return
	}
	terms := req.URL.Path[1:]
	if terms == "" {
		terms = "字"
	}
	render(terms, w)
}

func serve() {
	http.HandleFunc("/", handler)
	err := http.ListenAndServe(*port, nil)
	if err != nil {
		log.Fatal("ListenAndServe: ", err)
	}
}

func main() {
	serve()
}