<?php
declare(strict_types=1);

namespace App\Support;

use ZipArchive;

final class GoogleEarthService
{
    public function viewUrl(float $lat, float $lng, string $label = ''): string
    {
        $query = sprintf('%.6F,%.6F', $lat, $lng);
        if ($label !== '') {
            $query = $label . ' ' . $query;
        }

        return 'https://earth.google.com/web/search/' . rawurlencode($query);
    }

    public function buildKml(array $properties, array $options = []): string
    {
        $scope = trim((string) ($options['scope'] ?? 'properties'));
        $documentName = $options['documentName'] ?? $this->defaultDocumentName($properties, $scope);
        $documentDescription = $options['documentDescription'] ?? $this->defaultDocumentDescription($properties, $scope);
        $votesMap = is_array($options['votesMap'] ?? null) ? $options['votesMap'] : [];
        $overlays = is_array($options['overlays'] ?? null) ? $options['overlays'] : [];

        $propertyPlacemarks = array_map(function (array $property) use ($votesMap): string {
            return $this->propertyPlacemark($property, $votesMap[(int) ($property['id'] ?? 0)] ?? []);
        }, $properties);
        $overlayPlacemarks = array_values(array_filter(array_map(
            fn (array $overlay): string => $this->overlayPlacemark($overlay),
            $overlays
        )));

        $folders = [];
        if ($propertyPlacemarks !== []) {
            $folders[] = "<Folder>\n<name>Property Opportunities</name>\n" . implode("\n", $propertyPlacemarks) . "\n</Folder>";
        }
        if ($overlayPlacemarks !== []) {
            $folders[] = "<Folder>\n<name>Spatial Overlays</name>\n" . implode("\n", $overlayPlacemarks) . "\n</Folder>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <name>{$this->xml($documentName)}</name>
    <description><![CDATA[{$documentDescription}]]></description>
    {$this->styleDefinitions()}
    {$this->styleMapDefinitions()}
    {$this->documentLookAt($properties)}
    {$this->joinXml($folders, 4)}
  </Document>
</kml>
XML;
    }

    public function buildKmzBinary(string $kml): ?string
    {
        if (!class_exists(ZipArchive::class)) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'sfc-earth-');
        if ($tempPath === false) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($tempPath);
            return null;
        }

        $zip->addFromString('doc.kml', $kml);
        $zip->close();

        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        return $binary === false ? null : $binary;
    }

    public function exportFileName(array $properties, string $scope = 'properties', string $format = 'kml'): string
    {
        $base = $this->slug($scope);
        if (count($properties) === 1) {
            $base = $this->slug((string) ($properties[0]['name'] ?? 'property'));
        }
        if ($base === '') {
            $base = 'locus-sf-google-earth-export';
        }

        return sprintf('%s-%s.%s', $base, gmdate('Ymd-His'), strtolower($format));
    }

    private function propertyPlacemark(array $property, array $votes): string
    {
        $lat = (float) ($property['lat'] ?? 0);
        $lng = (float) ($property['lng'] ?? 0);
        $type = strtolower((string) ($property['type'] ?? 'commercial'));
        $style = match ($type) {
            'logistics', 'manufacturing' => '#property-logistics',
            'hotel' => '#property-tourism',
            'bpo' => '#property-office',
            default => '#property-commercial',
        };

        $lookAt = <<<XML
<LookAt>
  <longitude>{$lng}</longitude>
  <latitude>{$lat}</latitude>
  <range>2200</range>
  <tilt>35</tilt>
  <heading>0</heading>
</LookAt>
XML;

        return <<<XML
<Placemark>
  <name>{$this->xml((string) ($property['name'] ?? 'Property Opportunity'))}</name>
  <styleUrl>{$style}</styleUrl>
  <description><![CDATA[{$this->propertyDescription($property, $votes)}]]></description>
  {$lookAt}
  {$this->extendedData($property, $votes)}
  <Point>
    <coordinates>{$lng},{$lat},0</coordinates>
  </Point>
</Placemark>
XML;
    }

    private function propertyDescription(array $property, array $votes): string
    {
        $readinessScore = (int) ($property['investmentReadiness']['totalScore'] ?? 0);
        $dueDiligencePct = (int) ($property['dueDiligencePct'] ?? 0);
        $documentCompletenessPct = (int) ($property['documentCompletenessPct'] ?? 0);
        $votesTotal = array_sum(array_map('intval', $votes));
        $topVote = $this->topVote($votes);

        $rows = [
            ['Corridor', $this->labelize((string) ($property['corridor'] ?? ''))],
            ['Barangay', (string) ($property['barangay'] ?? 'Unassigned')],
            ['Type', $this->labelize((string) ($property['type'] ?? 'Property'))],
            ['Price', $this->money((int) ($property['price'] ?? 0))],
            ['Lot Area', sprintf('%s ha', number_format((float) ($property['area'] ?? 0), 2))],
            ['Market Score', sprintf('%d / 100', (int) ($property['marketScore'] ?? 0))],
            ['Readiness', sprintf('%d / 100', $readinessScore)],
            ['Due Diligence', sprintf('%d%%', $dueDiligencePct)],
            ['Document Completeness', sprintf('%d%%', $documentCompletenessPct)],
            ['Listing Status', $this->labelize((string) ($property['listingVerificationStatus'] ?? 'unverified'))],
            ['Top Demand Signal', $topVote !== '' ? $topVote : 'No demand vote yet'],
            ['Vote Count', (string) $votesTotal],
        ];

        $tableRows = implode('', array_map(
            fn (array $row): string => '<tr><td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;"><strong>'
                . $this->html($row[0])
                . '</strong></td><td style="padding:6px 10px;border-bottom:1px solid #e5e7eb;">'
                . $this->html($row[1])
                . '</td></tr>',
            $rows
        ));

        return sprintf(
            '<div style="font-family:Arial,sans-serif;max-width:540px;color:#1f2937;">'
            . '<h2 style="margin:0 0 8px;font-size:20px;">%s</h2>'
            . '<p style="margin:0 0 14px;line-height:1.6;">%s</p>'
            . '<table style="width:100%%;border-collapse:collapse;font-size:13px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">%s</table>'
            . '</div>',
            $this->html((string) ($property['name'] ?? 'Property Opportunity')),
            $this->html((string) ($property['description'] ?? 'Spatial site review export from LOCUS-SF.')),
            $tableRows
        );
    }

    private function extendedData(array $property, array $votes): string
    {
        $pairs = [
            'property_id' => (string) ($property['id'] ?? ''),
            'corridor' => (string) ($property['corridor'] ?? ''),
            'barangay' => (string) ($property['barangay'] ?? ''),
            'type' => (string) ($property['type'] ?? ''),
            'price' => (string) ($property['price'] ?? ''),
            'area' => (string) ($property['area'] ?? ''),
            'market_score' => (string) ($property['marketScore'] ?? ''),
            'readiness_score' => (string) ($property['investmentReadiness']['totalScore'] ?? ''),
            'due_diligence_pct' => (string) ($property['dueDiligencePct'] ?? ''),
            'document_completeness_pct' => (string) ($property['documentCompletenessPct'] ?? ''),
            'listing_verification_status' => (string) ($property['listingVerificationStatus'] ?? ''),
            'top_demand_signal' => $this->topVote($votes),
            'vote_total' => (string) array_sum(array_map('intval', $votes)),
        ];

        $fields = array_map(
            fn (string $key, string $value): string => sprintf(
                '<Data name="%s"><value>%s</value></Data>',
                $this->xml($key),
                $this->xml($value)
            ),
            array_keys($pairs),
            array_values($pairs)
        );

        return "<ExtendedData>\n" . implode("\n", $fields) . "\n</ExtendedData>";
    }

    private function overlayPlacemark(array $overlay): string
    {
        $geometryType = strtolower((string) ($overlay['geometryType'] ?? 'polygon'));
        $geometry = is_array($overlay['geometry'] ?? null) ? $overlay['geometry'] : [];
        $coordinates = $this->flattenCoordinates($geometry['coordinates'] ?? $geometry);
        if ($coordinates === []) {
            return '';
        }

        $coordinateString = implode(' ', array_map(
            fn (array $point): string => sprintf('%s,%s,0', $point[0], $point[1]),
            $coordinates
        ));

        $styleUrl = match ((string) ($overlay['overlayType'] ?? '')) {
            'corridor' => '#overlay-corridor',
            'barangay' => '#overlay-barangay',
            'zoning' => '#overlay-zoning',
            default => '#overlay-parcel',
        };

        $geometryMarkup = match ($geometryType) {
            'line', 'linestring' => "<LineString><tessellate>1</tessellate><coordinates>{$coordinateString}</coordinates></LineString>",
            'point' => "<Point><coordinates>{$coordinateString}</coordinates></Point>",
            default => "<Polygon><outerBoundaryIs><LinearRing><coordinates>{$coordinateString}</coordinates></LinearRing></outerBoundaryIs></Polygon>",
        };

        return <<<XML
<Placemark>
  <name>{$this->xml((string) ($overlay['name'] ?? 'Spatial Overlay'))}</name>
  <styleUrl>{$styleUrl}</styleUrl>
  <description><![CDATA[{$this->overlayDescription($overlay)}]]></description>
  {$geometryMarkup}
</Placemark>
XML;
    }

    private function overlayDescription(array $overlay): string
    {
        return sprintf(
            '<div style="font-family:Arial,sans-serif;max-width:460px;color:#1f2937;">'
            . '<h3 style="margin:0 0 8px;">%s</h3>'
            . '<p style="margin:0;line-height:1.6;">%s</p>'
            . '</div>',
            $this->html((string) ($overlay['name'] ?? 'Spatial Overlay')),
            $this->html((string) ($overlay['description'] ?? 'Overlay exported from LOCUS-SF for Google Earth validation.'))
        );
    }

    private function documentLookAt(array $properties): string
    {
        if ($properties === []) {
            return '';
        }

        $latitudes = array_map(static fn (array $property): float => (float) ($property['lat'] ?? 0), $properties);
        $longitudes = array_map(static fn (array $property): float => (float) ($property['lng'] ?? 0), $properties);

        $lat = round((min($latitudes) + max($latitudes)) / 2, 6);
        $lng = round((min($longitudes) + max($longitudes)) / 2, 6);

        return <<<XML
<LookAt>
  <longitude>{$lng}</longitude>
  <latitude>{$lat}</latitude>
  <range>8000</range>
  <tilt>20</tilt>
  <heading>0</heading>
</LookAt>
XML;
    }

    private function defaultDocumentName(array $properties, string $scope): string
    {
        if (count($properties) === 1) {
            return sprintf('%s | Google Earth Export', (string) ($properties[0]['name'] ?? 'Property'));
        }

        return sprintf('LOCUS-SF | %s export', $this->labelize($scope !== '' ? $scope : 'properties'));
    }

    private function defaultDocumentDescription(array $properties, string $scope): string
    {
        return sprintf(
            '<div style="font-family:Arial,sans-serif;color:#1f2937;">'
            . '<strong>LOCUS-SF</strong><br>'
            . 'Google Earth export for %d property opportunity%s under the %s workflow.<br>'
            . 'Use this as a complementary spatial inspection layer for ranking, readiness, and validation.'
            . '</div>',
            count($properties),
            count($properties) === 1 ? '' : 'ies',
            $this->html($this->labelize($scope !== '' ? $scope : 'property'))
        );
    }

    private function styleDefinitions(): string
    {
        return <<<XML
<Style id="property-commercial"><IconStyle><scale>1.1</scale><color>ff007aff</color><Icon><href>http://maps.google.com/mapfiles/kml/paddle/orange-circle.png</href></Icon></IconStyle></Style>
<Style id="property-logistics"><IconStyle><scale>1.1</scale><color>ff0055ff</color><Icon><href>http://maps.google.com/mapfiles/kml/paddle/red-circle.png</href></Icon></IconStyle></Style>
<Style id="property-tourism"><IconStyle><scale>1.1</scale><color>ff2ec4ff</color><Icon><href>http://maps.google.com/mapfiles/kml/paddle/ylw-circle.png</href></Icon></IconStyle></Style>
<Style id="property-office"><IconStyle><scale>1.1</scale><color>ffcc6600</color><Icon><href>http://maps.google.com/mapfiles/kml/paddle/blu-circle.png</href></Icon></IconStyle></Style>
<Style id="overlay-parcel"><LineStyle><color>ff007aff</color><width>2.4</width></LineStyle><PolyStyle><color>33007aff</color></PolyStyle></Style>
<Style id="overlay-barangay"><LineStyle><color>ff00a65a</color><width>2.2</width></LineStyle><PolyStyle><color>2200a65a</color></PolyStyle></Style>
<Style id="overlay-corridor"><LineStyle><color>ff0055ff</color><width>2.6</width></LineStyle><PolyStyle><color>220055ff</color></PolyStyle></Style>
<Style id="overlay-zoning"><LineStyle><color>ff1b91ff</color><width>2.2</width></LineStyle><PolyStyle><color>221b91ff</color></PolyStyle></Style>
XML;
    }

    private function styleMapDefinitions(): string
    {
        return '';
    }

    private function flattenCoordinates(mixed $coordinates): array
    {
        if (!is_array($coordinates)) {
            return [];
        }

        $flattened = $coordinates;
        while (is_array($flattened) && isset($flattened[0]) && is_array($flattened[0]) && isset($flattened[0][0]) && is_array($flattened[0][0])) {
            $flattened = $flattened[0];
        }

        $points = [];
        foreach ($flattened as $point) {
            if (!is_array($point) || count($point) < 2) {
                continue;
            }

            $lng = float_or_null($point[0] ?? null);
            $lat = float_or_null($point[1] ?? null);
            if ($lat === null || $lng === null) {
                continue;
            }

            $points[] = [round($lng, 6), round($lat, 6)];
        }

        if (count($points) > 2 && $points[0] !== $points[count($points) - 1]) {
            $points[] = $points[0];
        }

        return $points;
    }

    private function topVote(array $votes): string
    {
        if ($votes === []) {
            return '';
        }

        arsort($votes);
        $label = (string) array_key_first($votes);
        return $label;
    }

    private function money(int $value): string
    {
        return 'PHP ' . number_format($value);
    }

    private function labelize(string $value): string
    {
        $normalized = trim((string) preg_replace('/[_-]+/', ' ', strtolower($value)));
        if ($normalized === '') {
            return '';
        }

        return ucwords($normalized);
    }

    private function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($value)), '-');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function joinXml(array $nodes, int $indentSpaces = 0): string
    {
        $indent = str_repeat(' ', $indentSpaces);
        return implode("\n", array_map(fn (string $node): string => $indent . trim($node), $nodes));
    }
}
