'use client';

import { useState } from "react";
import FormField from "./FormField";
import StatusMessage from "./StatusMessage";
import { apiRequest, getApiErrorMessage } from "@/lib/api";

function openStreetMapUrl(place) {
  const lat = Number(place.lat);
  const lon = Number(place.lon);
  const delta = 0.015;
  const bounds = [
    (lon - delta).toFixed(5),
    (lat - delta).toFixed(5),
    (lon + delta).toFixed(5),
    (lat + delta).toFixed(5),
  ].join(",");

  return `https://www.openstreetmap.org/export/embed.html?bbox=${bounds}&layer=mapnik&marker=${lat},${lon}`;
}

export default function WeatherMapPanel() {
  const [locationStatus, setLocationStatus] = useState({ type: "info", text: "Choose your location to request local weather." });
  const [weather, setWeather] = useState(null);
  const [placeQuery, setPlaceQuery] = useState("");
  const [places, setPlaces] = useState([]);
  const [selectedPlace, setSelectedPlace] = useState(null);
  const [isLocating, setIsLocating] = useState(false);
  const [isSearching, setIsSearching] = useState(false);

  async function loadWeather(latitude, longitude) {
    setWeather(null);
    setLocationStatus({ type: "info", text: "Loading current weather..." });

    try {
      const query = new URLSearchParams({ lat: String(latitude), lon: String(longitude) });
      const result = await apiRequest(`weather?${query}`);
      setWeather(result);
      setSelectedPlace({ lat: latitude, lon: longitude, name: result.location });
      setLocationStatus({ type: "", text: "" });
    } catch (error) {
      setLocationStatus({ type: "error", text: getApiErrorMessage(error) });
    }
  }

  function requestLocation() {
    if (!navigator.geolocation) {
      setLocationStatus({ type: "error", text: "Location is not available in this browser. Search for a place below instead." });
      return;
    }

    setIsLocating(true);
    setLocationStatus({ type: "info", text: "Waiting for location permission..." });
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setIsLocating(false);
        loadWeather(coords.latitude, coords.longitude);
      },
      (error) => {
        setIsLocating(false);
        const message =
          error.code === error.PERMISSION_DENIED
            ? "Location permission was denied. You can still search for a place below."
            : error.code === error.TIMEOUT
              ? "Location lookup timed out. Try again or search for a place."
              : "Your location could not be determined. Try again or search for a place.";
        setLocationStatus({ type: "error", text: message });
      },
      { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 },
    );
  }

  async function searchPlaces(event) {
    event.preventDefault();
    setIsSearching(true);
    setPlaces([]);
    setSelectedPlace(null);
    setLocationStatus({ type: "info", text: "Searching OpenStreetMap..." });

    try {
      const result = await apiRequest("maps/search", {
        method: "POST",
        body: { query: placeQuery },
      });
      setPlaces(result.places);
      setLocationStatus(
        result.places.length
          ? { type: "", text: "" }
          : { type: "info", text: "No places matched that search. Try a more specific location." },
      );
    } catch (error) {
      setLocationStatus({ type: "error", text: getApiErrorMessage(error) });
    } finally {
      setIsSearching(false);
    }
  }

  function selectPlace(place) {
    setSelectedPlace(place);
    setWeather(null);
    setLocationStatus({ type: "info", text: "Place selected. Request weather for your current location to view local conditions." });
  }

  return (
    <section className="mt-8 grid gap-5 lg:grid-cols-2">
      <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Current weather</h2>
        <p className="mt-1 text-sm text-slate-600">Weather is looked up only after you allow location access.</p>
        <button
          type="button"
          onClick={requestLocation}
          disabled={isLocating}
          className="mt-4 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-60"
        >
          {isLocating ? "Finding location..." : "Use my location"}
        </button>
        {weather ? (
          <div className="mt-4 rounded-xl bg-slate-50 p-4" aria-live="polite">
            <p className="font-semibold text-slate-900">{weather.location}</p>
            <p className="mt-1 text-3xl font-bold text-slate-900">{Math.round(weather.temperature_c)}°C</p>
            <p className="mt-1 capitalize text-slate-600">{weather.description}</p>
            <p className="mt-2 text-xs text-slate-500">
              Feels like {weather.feels_like_c ?? "—"}°C · Humidity {weather.humidity_percent ?? "—"}%
            </p>
            <p className="mt-1 text-xs text-slate-500">Source: {weather.provider}</p>
          </div>
        ) : null}
      </article>

      <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 className="text-lg font-semibold text-slate-900">Map search</h2>
        <p className="mt-1 text-sm text-slate-600">Search real places with OpenStreetMap.</p>
        <form className="mt-4 flex items-end gap-3" onSubmit={searchPlaces}>
          <div className="min-w-0 flex-1">
            <FormField
              id="mapPlaceQuery"
              label="Place or address"
              value={placeQuery}
              onChange={(event) => setPlaceQuery(event.target.value)}
              placeholder="Search a city or address"
              required
            />
          </div>
          <button
            type="submit"
            disabled={isSearching}
            className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
          >
            {isSearching ? "Searching..." : "Search"}
          </button>
        </form>
        {places.length ? (
          <ul className="mt-3 max-h-40 space-y-2 overflow-auto">
            {places.map((place) => (
              <li key={`${place.lat},${place.lon}`}>
                <button
                  type="button"
                  onClick={() => selectPlace(place)}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                >
                  {place.name}
                </button>
              </li>
            ))}
          </ul>
        ) : null}
        {selectedPlace ? (
          <div className="mt-4 overflow-hidden rounded-xl border border-slate-200">
            <iframe
              title={`OpenStreetMap: ${selectedPlace.name || "selected location"}`}
              src={openStreetMapUrl(selectedPlace)}
              className="h-64 w-full"
              loading="lazy"
              referrerPolicy="no-referrer"
            />
            <p className="p-2 text-xs text-slate-500">
              Map data © <a className="underline" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap contributors</a>
            </p>
          </div>
        ) : (
          <p className="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Search for a place or allow location access to display a map.</p>
        )}
      </article>

      <div className="lg:col-span-2">
        <StatusMessage type={locationStatus.type} text={locationStatus.text} />
      </div>
    </section>
  );
}
