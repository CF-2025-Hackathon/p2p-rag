package api

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"github.com/gin-gonic/gin"
	"io"
	"net/http"
	"net/http/httptest"
	"reflect"

	"github.com/cucumber/godog"
)

type godogsResponseCtxKey struct{}

type apiFeature struct {
	router *gin.Engine
}

type response struct {
	status int
	body   string
}

func (a *apiFeature) resetResponse(*godog.Scenario) {
	a.router = SetupRouter()
}

func (a *apiFeature) iSendRequestTo(ctx context.Context, method, route string) (context.Context, error) {
	return a.iSendRequestToWithPayload(ctx, method, route, nil)
}

func (a *apiFeature) iSendRequestToWithPayload(ctx context.Context, method, route string, payloadDoc *godog.DocString) (context.Context, error) {
	var r io.Reader
	if payloadDoc != nil {
		r = bytes.NewReader([]byte(payloadDoc.Content))
	} else {
		r = bytes.NewReader([]byte(""))
	}

	w := httptest.NewRecorder()

	req, _ := http.NewRequest(method, route, r)
	a.router.ServeHTTP(w, req)

	actual := response{
		status: w.Code,
		body:   w.Body.String(),
	}

	return context.WithValue(ctx, godogsResponseCtxKey{}, actual), nil
}

func (a *apiFeature) theResponseCodeShouldBe(ctx context.Context, expectedStatus int) error {
	resp, ok := ctx.Value(godogsResponseCtxKey{}).(response)
	if !ok {
		return errors.New("there are no godogs available")
	}

	if expectedStatus != resp.status {
		if resp.status >= 400 {
			return fmt.Errorf("expected response code to be: %d, but actual is: %d, response message: %s", expectedStatus, resp.status, resp.body)
		}
		return fmt.Errorf("expected response code to be: %d, but actual is: %d", expectedStatus, resp.status)
	}

	return nil
}

func (a *apiFeature) theResponsePayloadShouldMatchJson(ctx context.Context, body *godog.DocString) (err error) {
	resp, _ := ctx.Value(godogsResponseCtxKey{}).(response)

	var expected, actual interface{}

	// re-encode expected response
	if err = json.Unmarshal([]byte(body.Content), &expected); err != nil {
		return
	}

	// re-encode actual response too
	if err = json.Unmarshal([]byte(resp.body), &actual); err != nil {
		return
	}

	// the matching may be adapted per different requirements.
	if !reflect.DeepEqual(expected, actual) {
		return fmt.Errorf("expected JSON does not match actual, %v vs. %v", expected, actual)
	}
	return nil
}

func InitializeScenario(ctx *godog.ScenarioContext) {
	apiFeature := &apiFeature{}

	ctx.Before(func(ctx context.Context, sc *godog.Scenario) (context.Context, error) {
		apiFeature.resetResponse(sc)
		return ctx, nil
	})

	ctx.Step(`^I send "([^"]*)" request to "([^"]*)" with payload:$`, apiFeature.iSendRequestToWithPayload)
	ctx.Step(`^I send "([^"]*)" request to "([^"]*)"`, apiFeature.iSendRequestTo)
	ctx.Step(`^the response code should be (\d+)$`, apiFeature.theResponseCodeShouldBe)
	ctx.Step(`^the response payload should match json:$`, apiFeature.theResponsePayloadShouldMatchJson)
}
