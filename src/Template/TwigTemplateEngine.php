<?php

namespace ApeliMailers\Template;

interface TemplateEngineInterface
{
    public function render(string $templatePath, array $data): string;
}

/**
 * Simple template engine that replaces placeholders with values
 */
class SimpleTemplateEngine implements TemplateEngineInterface
{
    private string $basePath;
    private array $globals = [];
    private array $options = [];

    /**
     * @param string $templatePath Base path for templates
     * @param array $options Configuration options
     */
    public function __construct(string $templatePath, array $options = [])
    {
        $this->basePath = rtrim($templatePath, '/\\') . DIRECTORY_SEPARATOR;
        $this->options = $options;
    }

    /**
     * Render a template with the given data
     *
     * @param string $template Template file name relative to base path
     * @param array $data Variables to be injected into template
     * @return string Rendered template
     * @throws \RuntimeException If template cannot be loaded
     */
    public function render(string $template, array $data = []): string
    {
        $templateFullPath = $this->basePath . $template;
        
        if (!file_exists($templateFullPath)) {
            throw new \RuntimeException("Template file not found: {$templateFullPath}");
        }
        
        $content = file_get_contents($templateFullPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read template: {$templateFullPath}");
        }
        
        // Merge global variables with template-specific data
        $mergedData = array_merge($this->globals, $data);
        
        // Process includes (simplified version)
        if (!empty($this->options['process_includes']) && $this->options['process_includes'] === true) {
            $content = $this->processIncludes($content);
        }
        
        // Replace placeholders like {{ variable }} with their values
        $content = $this->replacePlaceholders($content, $mergedData);
        
        // Process basic conditionals if enabled
        if (!empty($this->options['process_conditionals']) && $this->options['process_conditionals'] === true) {
            $content = $this->processConditionals($content, $mergedData);
        }
        
        return $content;
    }

    /**
     * Add a global variable available to all templates
     *
     * @param string $name Variable name
     * @param mixed $value Variable value
     */
    public function addGlobal(string $name, $value): void
    {
        $this->globals[$name] = $value;
    }
    
    /**
     * Replace placeholders in template with their values
     * 
     * @param string $content Template content
     * @param array $data Data to inject
     * @return string Processed content
     */
    private function replacePlaceholders(string $content, array $data): string
    {
        $pattern = '/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/';
        
        return preg_replace_callback($pattern, function($matches) use ($data) {
            $key = $matches[1];
            
            // Support for nested data with dot notation (example: user.name)
            if (strpos($key, '.') !== false) {
                $parts = explode('.', $key);
                $value = $data;
                
                foreach ($parts as $part) {
                    if (!isset($value[$part])) {
                        return '';  // Key not found, return empty string
                    }
                    $value = $value[$part];
                }
                
                return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            }
            
            return isset($data[$key]) 
                ? htmlspecialchars((string)$data[$key], ENT_QUOTES, 'UTF-8') 
                : '';
        }, $content);
    }
    
    /**
     * Process template includes (simplified version)
     * Syntax: {% include 'template.html' %}
     * 
     * @param string $content Template content
     * @return string Processed content
     */
    private function processIncludes(string $content): string
    {
        $pattern = '/\{%\s*include\s+[\'"](.*?)[\'"].*?%\}/';
        
        return preg_replace_callback($pattern, function($matches) {
            $includePath = $this->basePath . $matches[1];
            
            if (file_exists($includePath)) {
                return file_get_contents($includePath) ?: '';
            }
            
            return '';  // File not found, return empty string
        }, $content);
    }
    
    /**
     * Process basic conditionals
     * Syntax: {% if variable %} content {% endif %}
     * 
     * @param string $content Template content
     * @param array $data Template data
     * @return string Processed content
     */
    private function processConditionals(string $content, array $data): string
    {
        $pattern = '/\{%\s*if\s+([a-zA-Z0-9_\.]+)\s*%\}(.*?)\{%\s*endif\s*%\}/s';
        
        return preg_replace_callback($pattern, function($matches) use ($data) {
            $key = $matches[1];
            $conditionalContent = $matches[2];
            
            // Support for nested data with dot notation
            if (strpos($key, '.') !== false) {
                $parts = explode('.', $key);
                $value = $data;
                
                foreach ($parts as $part) {
                    if (!isset($value[$part])) {
                        return '';  // Key not found, condition fails
                    }
                    $value = $value[$part];
                }
                
                return $value ? $conditionalContent : '';
            }
            
            return (isset($data[$key]) && $data[$key]) ? $conditionalContent : '';
        }, $content);
    }
}