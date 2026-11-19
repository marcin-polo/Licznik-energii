# Odczyt licznika energii (Modbus TCP, PHP)

Dwa kontenery z jednego obrazu:
- **poller** – co `POLL_INTERVAL` s czyta licznik i zapisuje stan do `/data/state.json`,
- **web** – strona `http://<host>:8502/` oraz JSON `http://<host>:8502/api.php`
  (`?values` = same wartości, `?history&seconds=300&keys=a,b` = historia do 1 h).

Stan i historia są trzymane w RAM (wolumen tmpfs) – po restarcie historia zaczyna się od nowa.

## Uruchomienie

```
git clone https://github.com/marcin-polo/Licznik-energii.git
cd Licznik-energii
cp .env.example .env      # ustaw typ i adres licznika (albo później w panelu)
docker compose up -d --build
```

Strona: `http://<host>:8502/`, panel: `http://<host>:8502/admin` (hasło domyślne `admin` – zmień je).

## Konfiguracja – `.env`

```
METER_TYPE=orno_we517        # domyślny licznik (nazwa pliku z meters/); zmiana także w panelu
METER_HOST=192.168.5.10
METER_PORT=502
METER_SLAVE=1
POLL_INTERVAL=1              # domyślny interwał [s]; zmiana w locie: panel → „Odczyt” (0,1 s – 1 min)
```

Po zmianie: `docker compose up -d`.

## Liczniki – `meters/*.php`, wybór w panelu → „Odczyt”

| plik | licznik | rodzaj | mapa |
|---|---|---|---|
| `orno_we517` | ORNO WE-517 | 3-faz., RS485 | **sprawdzona** |
| `orno_we516` | ORNO WE-516 | 3-faz., RS485 | jak WE-517 |
| `eastron_sdm630` | Eastron SDM630 (v2/MCT) | 3-faz., RS485 | dokumentacja |
| `eastron_sdm72dm` | Eastron SDM72D-M v2 | 3-faz., RS485 | dokumentacja |
| `eastron_sdm120` | Eastron SDM120 | 1-faz., RS485 | dokumentacja |
| `eastron_sdm230` | Eastron SDM230 | 1-faz., RS485 | dokumentacja |
| `hiking_dds238` | Hiking DDS238-2 ZN/S | 1-faz., RS485 | dokumentacja |
| `chint_dtsu666` | CHINT DTSU666 / Huawei DTSU666-H | 3-faz., RS485 (PV) | dokumentacja |
| `inepro_pro380` | inepro PRO380-Mod | 3-faz., RS485 (EV) | dokumentacja |
| `abb_b23` | ABB B21 / B23 / B24 | 3-faz., RS485/TCP | dokumentacja |
| `carlo_gavazzi_em340` | Carlo Gavazzi EM340 / EM24 | 3-faz., RS485 | dokumentacja |
| `siemens_pac2200` | Siemens SENTRON PAC2200 | 3-faz., TCP | dokumentacja |
| `siemens_pac3200` | Siemens SENTRON PAC3200 | 3-faz., TCP | dokumentacja |
| `siemens_pac4200` | Siemens SENTRON PAC4200 | 3-faz., TCP | dokumentacja |
| `janitza_umg96rm` | Janitza UMG 96RM / 604 / 509 | analizator, TCP/RTU | dokumentacja |
| `schneider_pm5000` | Schneider PowerLogic PM5000 | miernik, TCP/RTU | dokumentacja |

Mapy „dokumentacja” nie były sprawdzone na urządzeniu – przed użyciem kliknij „Testuj” w panelu
(odczytuje wszystkie wartości bez zmiany konfiguracji). Wszystkie mapy używają tych samych nazw wartości
(`voltage_l1`, `power_active_total`, `energy_active_import`…), więc ekrany, progi i raporty działają z każdym licznikiem.
Liczniki RS485 podłącza się przez bramkę RS485→Modbus TCP (adres bramki + Unit ID licznika na magistrali).

Własny licznik: skopiuj najbliższy plik, zmień adresy (od 0, jak w Home Assistant), typy (`int16`…`uint64`, `float32`, `float64`),
`scale`, `function` (3/4) i `word_order` (`big` = ABCD, `little` = CDAB). Plik pojawi się w panelu od razu.

Połączenie (typ, adres, port, Unit ID, timeout) ustawia się w panelu; puste pola = wartości z `.env`.
Zmiana licznika działa w locie. Baza zapisuje identyfikator licznika przy każdym okresie, więc raporty
nie liczą różnicy między stanami energii różnych liczników.

## Panel konfiguracji – `http://<host>:8502/admin.php`

Domyślne hasło: **admin** (panel przypomina o zmianie). Hasło jest zapisywane jako hash w
`config/admin_password.txt` – usunięcie pliku przywraca hasło domyślne.

- **Ekrany** – dowolna liczba ekranów pod adresem `/d/<nazwa>`. Każdy ekran to do 6 wierszy
  (wysokość = waga względna), w każdym do 6 kolumn (szerokość = waga). Komórka: wartość, wykres
  (wiele serii, zakres 1 min – 1 h) lub zegar. Otwarte ekrany przebudowują się same po zapisaniu.
- **Progi i kolory** – kolory poziomów (norma, oddawanie, ostrzeżenie, alarm) i progi dla każdej wartości.

Ustawienia są w `config/settings.json` (brak pliku = ustawienia domyślne).
Doraźny ekran bez konfiguracji: `/d/?keys=power_active_l1,current_l1&kw`.

## Raport energii – `http://<host>:8502/raport`

Co 15 min (`DEMAND_MINUTES`) poller zapisuje do `data/energy.sqlite` średnią/min/max mocy (także na fazach)
i stany liczników energii. Raport: dzień (okresy 15-min), miesiąc (dni), rok (miesiące) – pobrana, oddana,
bilans, szczyt mocy 15-min; eksport CSV (średniki, polskie przecinki – otwiera się w Excelu).
Energia liczona z różnicy stanów liczników, więc przerwa w odczytach nie gubi energii (trafia do pierwszego okresu po przerwie).

Wartości wyliczane (do ekranów, progów i powiadomień jak zwykłe wartości): `demand_current` (średnia bieżącego okresu),
`demand_forecast` (prognoza na koniec okresu), `demand_previous`, `demand_peak_day`, `demand_peak_month`,
`energy_import_today`, `energy_export_today`, `energy_import_month`, `energy_export_month`.

Kopia zapasowa = skopiowanie katalogu `data/` (i `config/`).

## Powiadomienia

Panel → „Powiadomienia”: ntfy, Telegram, e-mail SMTP, przycisk „Wyślij test”. W „Progi i kolory” zaznacz „Powiadom”
przy pilnowanych wartościach. Pilnuje poller (bez otwartej przeglądarki): opóźnienie, przypominanie, powrót do normy,
brak komunikacji z licznikiem. Histereza (kolumna w progach) działa też na kolory. Historia zdarzeń – w panelu i w raporcie.

## Odczyt

Panel → „Odczyt”: interwał 0,1 s – 1 min, działa w ciągu kilku sekund bez restartu (zapisany w `config/settings.json`,
puste = `POLL_INTERVAL` z `.env`). Odczyt i zapis danych działa w tle w kontenerze `poller` – niezależnie od otwartej strony.
Panel pokazuje czas jednego odczytu; ORNO przez bramkę RS485 potrzebuje ok. 0,6–0,7 s, więc szybciej się nie da.

## Diagnostyka

Panel → „Diagnostyka”: odczyt dowolnych rejestrów (FC03/04) z dekodowaniem int16/32, float32 (ABCD/CDAB), float64.
Odczyt licznika z konfiguracji idzie przez połączenie pollera – bramki RS485→TCP (np. ta przed ORNO) przyjmują
często tylko jedno połączenie. Inne adresy (np. nowy licznik przed przełączeniem) – połączenie bezpośrednie.

## Logi

`docker compose logs -f poller`
