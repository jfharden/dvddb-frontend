package main

import (
	"crypto/tls"
	"fmt"
	"net/url"
	"strings"
	"testing"
	"time"

	"github.com/PuerkitoBio/goquery"
	http_helper "github.com/gruntwork-io/terratest/modules/http-helper"
	"github.com/stretchr/testify/assert"
)

type PageStructureOptions struct {
	SearchTerm           string
	NumberOfResults      int
	SelectedSearchFields *SelectedSearchFields
	ResultRows           []*ResultRow
}

func NewPageStructureOptions(searchTerm string, numberOfResults int) *PageStructureOptions {
	return &PageStructureOptions{
		SearchTerm:           searchTerm,
		NumberOfResults:      numberOfResults,
		SelectedSearchFields: &SelectedSearchFields{},
		ResultRows:           []*ResultRow{},
	}
}

type ResultRow struct {
	DBID  string
	Title string
	Year  string
}

type SelectedSearchFields struct {
	Years   []string
	Ratings []string
	Genres  []string
	Types   []string
}

type SearchFieldQuery struct {
	Form           *goquery.Selection
	Name           string
	ValuesInclude  map[string]string
	SelectedValues []string
}

func TestDVDDBFunctionality(t *testing.T) {
	t.Parallel()

	options := &RunTestOptions{
		DockerComposeFile: "docker-compose.yaml",
		Validators: map[string]func(*testing.T, *RunTestOptions){
			"ValidateEmptySearch":          validateEmptySearch,
			"ValidateSimpleSearch":         validateSimpleSearch,
			"ValidateSearchWithYear":       validateSearchWithYear,
			"ValidateSearchWithYearOnly":   validateSearchWithYearOnly,
			"ValidateSearchWithRating":     validateSearchWithRating,
			"ValidateSearchWithRatingOnly": validateSearchWithRatingOnly,
			"ValidateSearchWithGenre":      validateSearchWithGenre,
			"ValidateSearchWithGenreOnly":  validateSearchWithGenreOnly,
			"ValidateSearchWithType":       validateSearchWithType,
			"ValidateSearchWithTypeOnly":   validateSearchWithTypeOnly,
			"ValidateSearchWithAllFields":  validateSearchWithAllFields,
		},
		WaitForReady: func() { time.Sleep(10 * time.Second) },
		EnvVars: map[string]string{
			"HTTP_PORT": "8480",
		},
	}

	runTestsWithDockerComposeFile(t, options)
}

func validateEmptySearch(t *testing.T, options *RunTestOptions) {
	statusCode, body := http_helper.HttpGet(t, urlWithoutAuth(options, ""), &tls.Config{})

	assert.Equal(t, 200, statusCode)

	pageStructureOptions := NewPageStructureOptions("", 0)

	validateBasicPageStructure(t, body, pageStructureOptions)
}

func validateSimpleSearch(t *testing.T, options *RunTestOptions) {
	searchTerm := "Alien vs Predator"
	expectedNumberOfResults := 2

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "53",
			Title: "AVP: Alien vs. Predator",
			Year:  "2004",
		},
		&ResultRow{
			DBID:  "54",
			Title: "AVPR: Aliens vs Predator - Requiem",
			Year:  "2007",
		},
	}

	pageBody := performSearch(t, map[string][]string{
		"title": []string{searchTerm},
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

func validateSearchWithYear(t *testing.T, options *RunTestOptions) {
	searchTerm := "Harry Potter"
	expectedNumberOfResults := 2
	selectedYears := []string{"2002", "2004"}

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "370",
			Title: "Harry Potter and the Chamber of Secrets",
			Year:  "2002",
		},
		&ResultRow{
			DBID:  "377",
			Title: "Harry Potter and the Prisoner of Azkaban",
			Year:  "2004",
		},
	}
	pageStructureOptions.SelectedSearchFields.Years = selectedYears

	pageBody := performSearch(t, map[string][]string{
		"title":  []string{searchTerm},
		"year[]": selectedYears,
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

func validateSearchWithYearOnly(t *testing.T, options *RunTestOptions) {
	searchTerm := ""
	expectedNumberOfResults := 1
	selectedYears := []string{"1969"}

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "143",
			Title: "Hercules in New York",
			Year:  "1969",
		},
	}
	pageStructureOptions.SelectedSearchFields.Years = selectedYears

	pageBody := performSearch(t, map[string][]string{
		"title":  []string{searchTerm},
		"year[]": selectedYears,
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

func validateSearchWithRating(t *testing.T, options *RunTestOptions) {
	searchTerm := "Spider"
	expectedNumberOfResults := 1
	selectedRatings := []string{"TV-Y7"}

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "460",
			Title: "Spider-Man",
			Year:  "1967-1970",
		},
	}
	pageStructureOptions.SelectedSearchFields.Ratings = selectedRatings

	pageBody := performSearch(t, map[string][]string{
		"title":    []string{searchTerm},
		"rating[]": []string{"22"},
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

func validateSearchWithRatingOnly(t *testing.T, options *RunTestOptions) {
	searchTerm := ""
	expectedNumberOfResults := 2
	selectedRatings := []string{"TV-Y7"}

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "360",
			Title: "C.O.P.S.",
			Year:  "1988-1989",
		},
		&ResultRow{
			DBID:  "460",
			Title: "Spider-Man",
			Year:  "1967-1970",
		},
	}
	pageStructureOptions.SelectedSearchFields.Ratings = selectedRatings

	pageBody := performSearch(t, map[string][]string{
		"title":    []string{searchTerm},
		"rating[]": []string{"22"},
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

func validateSearchWithGenre(t *testing.T, options *RunTestOptions) {
	searchTerm := "AVP"
	expectedNumberOfResults := 2
	selectedGenres := []string{"Action"}

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "53",
			Title: "AVP: Alien vs. Predator",
			Year:  "2004",
		},
		&ResultRow{
			DBID:  "54",
			Title: "AVPR: Aliens vs Predator - Requiem",
			Year:  "2007",
		},
	}
	pageStructureOptions.SelectedSearchFields.Genres = selectedGenres

	pageBody := performSearch(t, map[string][]string{
		"title":   []string{searchTerm},
		"genre[]": []string{"37"},
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

func validateSearchWithGenreOnly(t *testing.T, options *RunTestOptions) {
	searchTerm := ""
	expectedNumberOfResults := 1
	selectedGenres := []string{"Talk-Show"}

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "170",
			Title: "Man to Man with Dean Learner",
			Year:  "2006",
		},
	}
	pageStructureOptions.SelectedSearchFields.Genres = selectedGenres

	pageBody := performSearch(t, map[string][]string{
		"title":   []string{searchTerm},
		"genre[]": []string{"50"},
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

func validateSearchWithType(t *testing.T, options *RunTestOptions) {
	searchTerm := "AVP"
	expectedNumberOfResults := 2
	selectedTypes := []string{"Movie"}

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "53",
			Title: "AVP: Alien vs. Predator",
			Year:  "2004",
		},
		&ResultRow{
			DBID:  "54",
			Title: "AVPR: Aliens vs Predator - Requiem",
			Year:  "2007",
		},
	}
	pageStructureOptions.SelectedSearchFields.Types = selectedTypes

	pageBody := performSearch(t, map[string][]string{
		"title":   []string{searchTerm},
		"types[]": []string{"6"},
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

func validateSearchWithTypeOnly(t *testing.T, options *RunTestOptions) {
	searchTerm := ""
	expectedNumberOfResults := 1
	selectedTypes := []string{"Video Game"}

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "308",
			Title: "Wild Arms",
			Year:  "1996",
		},
	}
	pageStructureOptions.SelectedSearchFields.Types = selectedTypes

	pageBody := performSearch(t, map[string][]string{
		"title":   []string{searchTerm},
		"types[]": []string{"10"},
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

func validateSearchWithAllFields(t *testing.T, options *RunTestOptions) {
	searchTerm := "week"
	expectedNumberOfResults := 2
	selectedYears := []string{"2007"}
	selectedRatings := []string{"R"}
	selectedGenres := []string{"Thriller"}
	selectedTypes := []string{"Movie"}

	pageStructureOptions := NewPageStructureOptions(searchTerm, expectedNumberOfResults)
	pageStructureOptions.ResultRows = []*ResultRow{
		&ResultRow{
			DBID:  "46",
			Title: "28 Weeks Later",
			Year:  "2007",
		},
		&ResultRow{
			DBID:  "84",
			Title: "Captivity",
			Year:  "2007",
		},
	}
	pageStructureOptions.SelectedSearchFields.Years = selectedYears
	pageStructureOptions.SelectedSearchFields.Ratings = selectedRatings
	pageStructureOptions.SelectedSearchFields.Genres = selectedGenres
	pageStructureOptions.SelectedSearchFields.Types = selectedTypes

	pageBody := performSearch(t, map[string][]string{
		"title":    []string{searchTerm},
		"year[]":   selectedYears,
		"rating[]": []string{"7"},
		"genre[]":  []string{"29"},
		"types[]":  []string{"6"},
	}, options)

	validateBasicPageStructure(t, pageBody, pageStructureOptions)
}

type SearchKeyValue struct {
	Key   string
	Value string
}

func performSearch(t *testing.T, searchFields map[string][]string, options *RunTestOptions) string {
	postData := url.Values{}
	for key, values := range searchFields {
		for _, value := range values {
			postData.Add(key, value)
		}
	}
	postBody := strings.NewReader(postData.Encode())

	headers := map[string]string{
		"Content-Type": "application/x-www-form-urlencoded",
	}

	statusCode, body := http_helper.HTTPDo(t, "POST", urlWithoutAuth(options, ""), postBody, headers, &tls.Config{})

	assert.Equal(t, 200, statusCode)

	return body
}

func validateBasicPageStructure(t *testing.T, htmlBody string, options *PageStructureOptions) {
	doc := htmlDocument(t, htmlBody)

	// Has the heading dvd db
	selection := doc.Find("h1")
	assert.Equal(t, 1, selection.Length())
	assert.Equal(t, "DVD DB", selection.Text())

	// Contains a show advanced options link
	selection = doc.Find("a#openButton")
	assert.Equal(t, "Show Advanced Options", selection.Text())
	href, hrefExists := selection.Attr("href")
	assert.True(t, hrefExists, "Advanced options link is on page")
	assert.Equal(t, "#", href, "Advanced options link is only an anchor")

	// Contains search form
	searchForm := doc.Find("form")
	assert.Equal(t, 1, searchForm.Length(), "Contains Search Form")
	// With the correct action
	action, actionExists := searchForm.Attr("action")
	assert.True(t, actionExists, "Form has an action attribute")
	assert.Equal(t, "index.php", action, "Action points back to the same form")
	// And the correct Method
	method, methodExists := searchForm.Attr("method")
	assert.True(t, methodExists, "Form has a method attribute")
	assert.Equal(t, "post", method, "Method is post")

	// Search form has a submit button
	submitButton := searchForm.Find("input[type=submit]")
	assert.Equal(t, 1, submitButton.Length(), "Form has a submit button")
	submitValue, submitValueExists := submitButton.Attr("value")
	assert.True(t, submitValueExists, "Submit input has a value")
	assert.Equal(t, "Search", submitValue, "Search button has Search as it's value")

	// Contains a search box
	searchField := searchForm.Find("input[name=title]")
	assert.Equal(t, 1, searchField.Length(), "Form has a search box")
	searchValue, searchValueExists := searchField.Attr("value")
	assert.True(t, searchValueExists, "Search field does not have a value attribute")
	assert.Equal(t, options.SearchTerm, searchValue, fmt.Sprintf("Search field has value %s", options.SearchTerm))

	// form with search boxes for year, certification, genre, type
	// and each has at least some values in
	containsSearchBox(t, &SearchFieldQuery{
		Form:           searchForm,
		Name:           "year",
		ValuesInclude:  map[string]string{"2015": "2015", "2011": "2011"},
		SelectedValues: options.SelectedSearchFields.Years,
	})
	containsSearchBox(t, &SearchFieldQuery{
		Form:           searchForm,
		Name:           "rating",
		ValuesInclude:  map[string]string{"13": "G", "11": "UNRATED"},
		SelectedValues: options.SelectedSearchFields.Ratings,
	})
	containsSearchBox(t, &SearchFieldQuery{
		Form:           searchForm,
		Name:           "genre",
		ValuesInclude:  map[string]string{"34": "Comedy", "39": "Western"},
		SelectedValues: options.SelectedSearchFields.Genres,
	})
	containsSearchBox(t, &SearchFieldQuery{
		Form:           searchForm,
		Name:           "types",
		ValuesInclude:  map[string]string{"8": "Video", "7": "TV Series"},
		SelectedValues: options.SelectedSearchFields.Types,
	})

	// Shows correct number of results
	resultsHeading := doc.Find("div.choice h2:contains(\"Discs\")")
	assert.Equal(t, 1, resultsHeading.Length(), "Has a results heading")
	assert.Equal(t,
		fmt.Sprintf("Discs (%d)", options.NumberOfResults),
		resultsHeading.Text(),
		"Has the correct number of results",
	)

	tableBody := doc.Find("table#discsTable tbody")

	numberOfRows := tableBody.Find("tr")
	assert.Equal(t, len(options.ResultRows), numberOfRows.Length(), "Incorrect number of rows for search")

	if len(options.ResultRows) > 0 {
		// Validate the results we are expecting in the options.ResultRows
		for _, expectedRow := range options.ResultRows {
			assertResultRowsContain(t, tableBody, expectedRow)
		}
	}
}

func assertResultRowsContain(t *testing.T, table *goquery.Selection, expectedRow *ResultRow) {
	row := table.Find(fmt.Sprintf("tr#groupRow_%s", expectedRow.DBID))
	assert.Equal(t,
		1,
		row.Length(),
		fmt.Sprintf("Incorrect number of rows with DB ID: %s (Title: %s)", expectedRow.DBID, expectedRow.Title),
	)

	titleColumn := row.Find("td").Eq(0)
	assert.Equal(t, 1, titleColumn.Length(), fmt.Sprintf("Title column for DBID %s not found", expectedRow.DBID))
	assert.Equal(t,
		expectedRow.Title,
		titleColumn.Text(),
		fmt.Sprintf("Title column for DBID %s incorrect", expectedRow.DBID),
	)

	yearColumn := row.Find("td").Eq(1)
	assert.Equal(t, 1, yearColumn.Length(), fmt.Sprintf("Year column for DBID %s not found", expectedRow.DBID))
	assert.Equal(t,
		expectedRow.Year,
		yearColumn.Text(),
		fmt.Sprintf("Year column for DBID %s incorrect", expectedRow.DBID),
	)
}

func containsSearchBox(t *testing.T, query *SearchFieldQuery) {
	field := query.Form.Find(fmt.Sprintf("select[name=%s\\[\\]]", query.Name))
	assert.Equal(t, 1, field.Length(), fmt.Sprintf("Form contains the select field %s[]", query.Name))
	multi, multiPresent := field.Attr("multiple")
	assert.True(t, multiPresent, "Has the attribute multiple")
	assert.Equal(t, "multiple", multi, "Is a multiple select list")

	for value, text := range query.ValuesInclude {
		option := field.Find(fmt.Sprintf("option[value=\"%s\"]", value))
		assert.Equal(t, 1, option.Length(), fmt.Sprintf("Search box %s contains value %s", query.Name, value))
		assert.Equal(t, text, option.Text(), "Search box %s value %s has text %s", query.Name, value, text)
	}

	// For every option in the actual form check that if it is, and should be, selected
	field.Find("option").Each(
		func(i int, option *goquery.Selection) {
			optionName := option.Text()

			selectedAttribute, selectedExists := option.Attr("selected")
			isSelected := selectedExists && selectedAttribute == "selected"
			shouldBeSelected := optionInArray(optionName, query.SelectedValues)

			assert.Equal(t,
				shouldBeSelected,
				isSelected,
				fmt.Sprintf("Option %s has selection status %t, should be %t", optionName, isSelected, shouldBeSelected),
			)
		},
	)
}

func optionInArray(option string, array []string) bool {
	for _, value := range array {
		if option == value {
			return true
		}
	}

	return false
}

func htmlDocument(t *testing.T, body string) *goquery.Document {
	document, err := goquery.NewDocumentFromReader(strings.NewReader(body))

	if err != nil {
		t.Fatal("Couldn't parse HTML body")
	}

	return document
}
