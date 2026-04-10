<?php

namespace App\Services;

use App\Models\VoiceNote;

class IssueContentSanitizer
{
    public function sanitizeAndWrap(VoiceNote $note): string
    {
        $body = $this->sanitize($note->processed_body ?? '');

        $sections = [];

        $sections[] = $body;

        $metadataSection = $this->buildMetadataSection($note);
        if ($metadataSection) {
            $sections[] = $metadataSection;
        }

        return implode("\n\n---\n\n", $sections);
    }

    private function sanitize(string $content): string
    {
        // Remove HTML comments (including agent directive injections)
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        // Remove script tags and their content
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);

        // Remove style tags and their content
        $content = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content);

        // Remove all HTML tags
        $content = strip_tags($content);

        // Remove javascript: URIs
        $content = preg_replace('/javascript\s*:/i', '', $content);

        // Remove data: URIs that could be malicious
        $content = preg_replace('/data\s*:\s*text\/html/i', '', $content);

        return trim($content);
    }

    private function buildMetadataSection(VoiceNote $note): ?string
    {
        if (empty($note->metadata)) {
            return null;
        }

        $typeConfig = config("herold.types.{$note->type}");
        $lines = [];

        foreach ($note->metadata as $key => $value) {
            $label = $key;

            if (isset($typeConfig['extra_fields'])) {
                foreach ($typeConfig['extra_fields'] as $field) {
                    if ($field['name'] === $key) {
                        $label = $field['label'];
                        break;
                    }
                }
            }

            $sanitizedValue = $this->sanitize((string) $value);
            $lines[] = "- **{$label}:** {$sanitizedValue}";
        }

        if (empty($lines)) {
            return null;
        }

        return "## Metadata\n\n".implode("\n", $lines);
    }
}
