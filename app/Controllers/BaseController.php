<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

/**
 * Basis-Controller für alle Controller in der Vertragsverwaltung
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
abstract class BaseController
{
    protected array $data = [];
    
    public function __construct()
    {
        $this->data = [
            'user' => auth(),
            'csrf_token' => csrf_token(),
        ];
    }
    
    /**
     * View rendern
     */
    protected function view(string $template, array $data = []): string
    {
        return View::render($template, array_merge($this->data, $data));
    }
    
    /**
     * JSON Response senden
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Redirect ausführen
     */
    protected function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header("Location: $url");
        exit;
    }
    
    /**
     * Redirect mit Flash Message
     */
    protected function redirectWithMessage(string $url, string $message, string $type = 'success'): void
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        $this->redirect($url);
    }
    
    /**
     * Input Validation Helper
     */
    protected function validate(array $rules): array
    {
        $data = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $_POST[$field] ?? $_GET[$field] ?? null;
            
            if (is_array($rule)) {
                // Multiple validation rules
                foreach ($rule as $singleRule) {
                    $result = $this->validateSingle($field, $value, $singleRule);
                    if ($result !== true) {
                        $errors[$field] = $result;
                        break;
                    }
                }
            } else {
                // Single rule
                $result = $this->validateSingle($field, $value, $rule);
                if ($result !== true) {
                    $errors[$field] = $result;
                }
            }
            
            if (!isset($errors[$field])) {
                $data[$field] = $value;
            }
        }
        
        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old_input'] = $_POST;
            throw new \InvalidArgumentException('Validation failed');
        }
        
        return $data;
    }
    
    /**
     * Single validation rule
     */
    private function validateSingle(string $field, $value, string $rule): string|bool
    {
        switch ($rule) {
            case 'required':
                return !empty($value) ?: "Das Feld $field ist erforderlich";
                
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ?: "Ungültige E-Mail-Adresse";
                
            case 'numeric':
                return is_numeric($value) ?: "Das Feld $field muss numerisch sein";
                
            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false ?: "Das Feld $field muss eine Ganzzahl sein";
                
            default:
                if (str_starts_with($rule, 'min:')) {
                    $min = (int)substr($rule, 4);
                    return strlen($value) >= $min ?: "Das Feld $field muss mindestens $min Zeichen haben";
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = (int)substr($rule, 4);
                    return strlen($value) <= $max ?: "Das Feld $field darf maximal $max Zeichen haben";
                }
                return true;
        }
    }
    
    /**
     * RBAC-Check Helper
     */
    protected function authorize(string $action, string $resource): void
    {
        if (!rbac_check($action, $resource)) {
            http_response_code(403);
            if ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? false) {
                $this->json(['error' => 'Zugriff verweigert'], 403);
            } else {
                $this->view('errors/403');
            }
            exit;
        }
    }
    
    /**
     * Pagination Helper
     */
    protected function paginate(array $items, int $perPage = 10): array
    {
        $page = (int)($_GET['page'] ?? 1);
        $offset = ($page - 1) * $perPage;
        $total = count($items);
        $totalPages = ceil($total / $perPage);
        
        return [
            'data' => array_slice($items, $offset, $perPage),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $totalPages ? $page + 1 : null,
            ]
        ];
    }
}