<?php
/**
 * Library Report Template Merging Utility
 * Merges uploaded letterhead templates with report data
 * Template becomes background layer, report data positioned below letterhead (Y: 50mm+)
 * Uses FPDI for PDF, PHPWord for DOCX, PhpSpreadsheet for Excel
 */

require_once __DIR__ . '/functions.php';

/**
 * Generate report with template overlay
 */
function generate_report_with_template($template_path, $report_data, $report_type = 'fines', $output_format = 'pdf') {
    if (!file_exists($template_path)) {
        error_log('Template not found: ' . $template_path);
        return false;
    }

    $file_ext = strtolower(pathinfo($template_path, PATHINFO_EXTENSION));

    if ($file_ext === 'pdf') {
        return merge_pdf_with_fpdi($template_path, $report_data, $report_type);
    } elseif (in_array($file_ext, ['docx', 'doc'])) {
        return merge_docx_with_phpword($template_path, $report_data, $report_type);
    } elseif (in_array($file_ext, ['xlsx', 'xls'])) {
        return merge_excel_with_phpspreadsheet($template_path, $report_data, $report_type);
    }

    error_log('Unsupported template format: ' . $file_ext);
    return false;
}

/**
 * Merge PDF template with report data using FPDI
 */
function merge_pdf_with_fpdi($template_path, $report_data, $report_type) {
    $fpdi_path = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($fpdi_path)) {
        error_log('FPDI library not installed. Creating PDF fallback.');
        return create_fallback_report_file($report_data, $report_type, 'pdf');
    }

    try {
        require_once $fpdi_path;
        
        if (!class_exists('setasign\\Fpdi\\Fpdi')) {
            error_log('FPDI class not found');
            return create_fallback_report_file($report_data, $report_type, 'pdf');
        }
        
        $pdf = new \setasign\Fpdi\Fpdi();
        $pageCount = $pdf->setSourceFile($template_path);
        
        for ($i = 1; $i <= $pageCount; $i++) {
            $templateId = $pdf->importPage($i);
            $pdf->addPage();
            $pdf->useTemplate($templateId);
            $pdf->SetY(50);
            $pdf->SetFont('Arial', '', 9);
            add_report_to_pdf($pdf, $report_data, $report_type);
        }
        
        $temp_file = sys_get_temp_dir() . '/report_' . time() . '_' . uniqid() . '.pdf';
        $pdf->Output($temp_file, 'F');
        
        return file_exists($temp_file) ? $temp_file : false;
        
    } catch (Exception $e) {
        error_log('PDF merge error: ' . $e->getMessage());
        return create_fallback_report_file($report_data, $report_type, 'pdf');
    }
}

/**
 * Merge DOCX template with report data using PHPWord
 */
function merge_docx_with_phpword($template_path, $report_data, $report_type) {
    $phpword_path = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($phpword_path)) {
        error_log('PHPWord library not installed. Creating DOCX fallback.');
        return create_fallback_report_file($report_data, $report_type, 'docx');
    }

    try {
        require_once $phpword_path;
        
        if (!class_exists('PhpOffice\\PhpWord\\PhpWord')) {
            error_log('PHPWord class not found');
            return create_fallback_report_file($report_data, $report_type, 'docx');
        }
        
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $doc = \PhpOffice\PhpWord\IOFactory::load($template_path);
        
        $section = $doc->addSection();
        $section->addTextBlock(['spacing' => 240])->addText('');
        
        add_report_to_docx($section, $report_data, $report_type);
        
        $temp_file = sys_get_temp_dir() . '/report_' . time() . '_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($doc, 'Word2007');
        $writer->save($temp_file);
        
        return file_exists($temp_file) ? $temp_file : false;
        
    } catch (Exception $e) {
        error_log('DOCX merge error: ' . $e->getMessage());
        return create_fallback_report_file($report_data, $report_type, 'docx');
    }
}

/**
 * Merge Excel template with report data using PhpSpreadsheet
 */
function merge_excel_with_phpspreadsheet($template_path, $report_data, $report_type) {
    $phpspreadsheet_path = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($phpspreadsheet_path)) {
        error_log('PhpSpreadsheet library not installed. Creating XLSX fallback.');
        return create_fallback_report_file($report_data, $report_type, 'xlsx');
    }

    try {
        require_once $phpspreadsheet_path;
        
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            error_log('PhpSpreadsheet class not found');
            return create_fallback_report_file($report_data, $report_type, 'xlsx');
        }
        
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($template_path);
        $sheet = $spreadsheet->getActiveSheet();
        
        add_report_to_excel($sheet, $report_data, $report_type, 20);
        
        $temp_file = sys_get_temp_dir() . '/report_' . time() . '_' . uniqid() . '.xlsx';
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($temp_file);
        
        return file_exists($temp_file) ? $temp_file : false;
        
    } catch (Exception $e) {
        error_log('Excel merge error: ' . $e->getMessage());
        return create_fallback_report_file($report_data, $report_type, 'xlsx');
    }
}

/**
 * Add report content to PDF
 */
function add_report_to_pdf(&$pdf, $report_data, $report_type) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(128, 0, 0);
    $pdf->Cell(0, 5, 'LIBRARY ' . strtoupper($report_type) . ' REPORT', 0, 1);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 4, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1);
    $pdf->Ln(3);
    
    if ($report_type === 'fines') {
        render_fines_table_pdf($pdf, $report_data);
    } elseif ($report_type === 'books') {
        render_books_table_pdf($pdf, $report_data);
    } elseif ($report_type === 'checkins') {
        render_checkins_table_pdf($pdf, $report_data);
    }
}

/**
 * Render fines table in PDF
 */
function render_fines_table_pdf(&$pdf, $report_data) {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(214, 168, 74);
    $pdf->Cell(45, 6, 'Name', 1, 0, 'L', true);
    $pdf->Cell(35, 6, 'Course', 1, 0, 'L', true);
    $pdf->Cell(35, 6, 'Total Fines', 1, 0, 'R', true);
    $pdf->Cell(20, 6, 'Status', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetFillColor(240, 240, 240);
    $fill = false;
    
    foreach ($report_data as $row) {
        $pdf->Cell(45, 5, substr($row['name'] ?? 'N/A', 0, 25), 1, 0, 'L', $fill);
        $pdf->Cell(35, 5, substr($row['course_year'] ?? 'N/A', 0, 15), 1, 0, 'L', $fill);
        $pdf->Cell(35, 5, '₱' . number_format($row['total_fines'] ?? 0, 2), 1, 0, 'R', $fill);
        $pdf->Cell(20, 5, $row['status'] ?? 'N/A', 1, 1, 'C', $fill);
        $fill = !$fill;
    }
}

/**
 * Render books table in PDF
 */
function render_books_table_pdf(&$pdf, $report_data) {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(214, 168, 74);
    $pdf->Cell(100, 6, 'Book Title', 1, 0, 'L', true);
    $pdf->Cell(25, 6, 'Borrows', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetFillColor(240, 240, 240);
    $fill = false;
    
    foreach ($report_data as $row) {
        $pdf->Cell(100, 5, substr($row['title'] ?? 'N/A', 0, 40), 1, 0, 'L', $fill);
        $pdf->Cell(25, 5, $row['borrow_count'] ?? '0', 1, 1, 'C', $fill);
        $fill = !$fill;
    }
}

/**
 * Render checkins table in PDF
 */
function render_checkins_table_pdf(&$pdf, $report_data) {
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(214, 168, 74);
    $pdf->Cell(30, 5, 'Name', 1, 0, 'L', true);
    $pdf->Cell(22, 5, 'Course', 1, 0, 'L', true);
    $pdf->Cell(25, 5, 'Check-in', 1, 0, 'C', true);
    $pdf->Cell(20, 5, 'Duration', 1, 0, 'C', true);
    $pdf->Cell(18, 5, 'Purpose', 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetFillColor(240, 240, 240);
    $fill = false;
    
    foreach ($report_data as $row) {
        $pdf->Cell(30, 4, substr($row['name'] ?? 'N/A', 0, 15), 1, 0, 'L', $fill);
        $pdf->Cell(22, 4, substr($row['course_year'] ?? 'N/A', 0, 10), 1, 0, 'L', $fill);
        $pdf->Cell(25, 4, substr($row['time_in'] ?? 'N/A', 0, 10), 1, 0, 'C', $fill);
        $pdf->Cell(20, 4, ($row['duration_minutes'] ?? '0') . 'min', 1, 0, 'C', $fill);
        $pdf->Cell(18, 4, substr($row['purpose'] ?? 'N/A', 0, 8), 1, 1, 'L', $fill);
        $fill = !$fill;
    }
}

/**
 * Add report content to Word document
 */
function add_report_to_docx(&$section, $report_data, $report_type) {
    $section->addText('LIBRARY ' . strtoupper($report_type) . ' REPORT', ['bold' => true, 'size' => 12]);
    $section->addText('Generated: ' . date('Y-m-d H:i:s'), ['size' => 9, 'italic' => true]);
    $section->addTextBlock(['spacing' => 240])->addText('');
    
    if ($report_type === 'fines') {
        render_fines_table_docx($section, $report_data);
    } elseif ($report_type === 'books') {
        render_books_table_docx($section, $report_data);
    } elseif ($report_type === 'checkins') {
        render_checkins_table_docx($section, $report_data);
    }
}

/**
 * Render fines table in DOCX
 */
function render_fines_table_docx(&$section, $report_data) {
    $table = $section->addTable(['border' => ['size' => 6, 'color' => '000000']]);
    $table->addRow();
    $table->addCell(2000)->addText('Name', ['bold' => true]);
    $table->addCell(1500)->addText('Course', ['bold' => true]);
    $table->addCell(1500)->addText('Fines', ['bold' => true]);
    $table->addCell(1000)->addText('Status', ['bold' => true]);
    
    foreach ($report_data as $row) {
        $table->addRow();
        $table->addCell(2000)->addText($row['name'] ?? 'N/A');
        $table->addCell(1500)->addText($row['course_year'] ?? 'N/A');
        $table->addCell(1500)->addText('₱' . number_format($row['total_fines'] ?? 0, 2));
        $table->addCell(1000)->addText($row['status'] ?? 'N/A');
    }
}

/**
 * Render books table in DOCX
 */
function render_books_table_docx(&$section, $report_data) {
    $table = $section->addTable(['border' => ['size' => 6, 'color' => '000000']]);
    $table->addRow();
    $table->addCell(3000)->addText('Book Title', ['bold' => true]);
    $table->addCell(1000)->addText('Borrows', ['bold' => true]);
    
    foreach ($report_data as $row) {
        $table->addRow();
        $table->addCell(3000)->addText($row['title'] ?? 'N/A');
        $table->addCell(1000)->addText($row['borrow_count'] ?? '0');
    }
}

/**
 * Render checkins table in DOCX
 */
function render_checkins_table_docx(&$section, $report_data) {
    $table = $section->addTable(['border' => ['size' => 6, 'color' => '000000']]);
    $table->addRow();
    $table->addCell(1200)->addText('Name', ['bold' => true]);
    $table->addCell(1000)->addText('Course', ['bold' => true]);
    $table->addCell(1000)->addText('Check-in', ['bold' => true]);
    $table->addCell(800)->addText('Duration', ['bold' => true]);
    $table->addCell(1000)->addText('Purpose', ['bold' => true]);
    
    foreach ($report_data as $row) {
        $table->addRow();
        $table->addCell(1200)->addText($row['name'] ?? 'N/A');
        $table->addCell(1000)->addText($row['course_year'] ?? 'N/A');
        $table->addCell(1000)->addText($row['time_in'] ?? 'N/A');
        $table->addCell(800)->addText(($row['duration_minutes'] ?? '0') . 'min');
        $table->addCell(1000)->addText($row['purpose'] ?? 'N/A');
    }
}

/**
 * Add report content to Excel sheet
 */
function add_report_to_excel(&$sheet, $report_data, $report_type, $start_row) {
    if ($report_type === 'fines') {
        $sheet->setCellValue('A' . $start_row, 'Name');
        $sheet->setCellValue('B' . $start_row, 'Course Year');
        $sheet->setCellValue('C' . $start_row, 'Total Fines');
        $sheet->setCellValue('D' . $start_row, 'Status');
        
        $row = $start_row + 1;
        foreach ($report_data as $item) {
            $sheet->setCellValue('A' . $row, $item['name'] ?? 'N/A');
            $sheet->setCellValue('B' . $row, $item['course_year'] ?? 'N/A');
            $sheet->setCellValue('C' . $row, $item['total_fines'] ?? 0);
            $sheet->setCellValue('D' . $row, $item['status'] ?? 'N/A');
            $row++;
        }
    } elseif ($report_type === 'books') {
        $sheet->setCellValue('A' . $start_row, 'Book Title');
        $sheet->setCellValue('B' . $start_row, 'Borrow Count');
        
        $row = $start_row + 1;
        foreach ($report_data as $item) {
            $sheet->setCellValue('A' . $row, $item['title'] ?? 'N/A');
            $sheet->setCellValue('B' . $row, $item['borrow_count'] ?? 0);
            $row++;
        }
    }
}

/**
 * Create fallback report file when libraries unavailable
 * Maintains format consistency - output extension matches template format
 */
function create_fallback_report_file($report_data, $report_type, $format) {
    if ($format === 'pdf') {
        $csv_file = create_csv_file($report_data, $report_type);
        if ($csv_file) {
            $pdf_file = str_replace('.csv', '.pdf', $csv_file);
            if (rename($csv_file, $pdf_file)) {
                return $pdf_file;
            }
        }
        return false;
    } elseif ($format === 'docx' || $format === 'doc') {
        return create_docx_file($report_data, $report_type);
    } elseif ($format === 'xlsx' || $format === 'xls') {
        $csv_file = create_csv_file($report_data, $report_type);
        if ($csv_file) {
            $xlsx_file = str_replace('.csv', '.xlsx', $csv_file);
            if (rename($csv_file, $xlsx_file)) {
                return $xlsx_file;
            }
        }
        return false;
    }
    return false;
}

/**
 * Create CSV file (fallback for PDF and Excel)
 */
function create_csv_file($report_data, $report_type) {
    if (empty($report_data)) {
        return false;
    }

    $csv = ucfirst($report_type) . " Report\n";
    $csv .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

    if ($report_type === 'fines') {
        $csv .= "Name,Course Year,Total Fines,Status\n";
        foreach ($report_data as $row) {
            $name = isset($row['name']) ? str_replace('"', '""', $row['name']) : 'N/A';
            $course = isset($row['course_year']) ? str_replace('"', '""', $row['course_year']) : 'N/A';
            $fines = isset($row['total_fines']) ? number_format($row['total_fines'], 2, '.', '') : '0.00';
            $status = isset($row['status']) ? $row['status'] : 'UNKNOWN';
            $csv .= '"' . $name . '","' . $course . '","' . $fines . '","' . $status . "\"\r\n";
        }
    } elseif ($report_type === 'books') {
        $csv .= "Book Title,Borrow Count\n";
        foreach ($report_data as $row) {
            $title = isset($row['title']) ? str_replace('"', '""', $row['title']) : 'N/A';
            $count = isset($row['borrow_count']) ? $row['borrow_count'] : '0';
            $csv .= '"' . $title . '","' . $count . "\"\r\n";
        }
    } elseif ($report_type === 'checkins') {
        $csv .= "Name,Course Year,Time In,Time Out,Duration (Minutes),Purpose\n";
        foreach ($report_data as $row) {
            $name = isset($row['name']) ? str_replace('"', '""', $row['name']) : 'N/A';
            $course = isset($row['course_year']) ? str_replace('"', '""', $row['course_year']) : 'N/A';
            $timeIn = isset($row['time_in']) ? $row['time_in'] : 'N/A';
            $timeOut = isset($row['time_out']) ? $row['time_out'] : 'Still Active';
            $duration = isset($row['duration_minutes']) ? $row['duration_minutes'] : '0';
            $purpose = isset($row['purpose']) ? str_replace('"', '""', $row['purpose']) : 'N/A';
            $csv .= '"' . $name . '","' . $course . '","' . $timeIn . '","' . $timeOut . '","' . $duration . '","' . $purpose . "\"\r\n";
        }
    }

    $temp_file = sys_get_temp_dir() . '/report_' . time() . '_' . uniqid() . '.csv';
    if (file_put_contents($temp_file, $csv) !== false) {
        return $temp_file;
    }
    return false;
}

/**
 * Create a proper DOCX file (ZIP-based Office Open XML format)
 * This is a valid Word document that can be opened without external libraries
 */
function create_docx_file($report_data, $report_type) {
    $temp_dir = sys_get_temp_dir() . '/docx_' . time() . '_' . uniqid();
    @mkdir($temp_dir);
    @mkdir($temp_dir . '/word');
    @mkdir($temp_dir . '/_rels');
    @mkdir($temp_dir . '/word/_rels');

    // Create [Content_Types].xml
    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';
    file_put_contents($temp_dir . '/[Content_Types].xml', $content_types);

    // Create _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
    file_put_contents($temp_dir . '/_rels/.rels', $rels);

    // Create word/document.xml
    $doc_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>';

    $doc_xml .= '
        <w:p>
            <w:pPr><w:pStyle w:val="Title"/></w:pPr>
            <w:r>
                <w:rPr><w:b/><w:sz w:val="28"/><w:color w:val="800000"/></w:rPr>
                <w:t>' . htmlspecialchars(ucfirst($report_type)) . ' Report</w:t>
            </w:r>
        </w:p>';

    $doc_xml .= '
        <w:p>
            <w:r>
                <w:rPr><w:i/><w:sz w:val="18"/><w:color w:val="666666"/></w:rPr>
                <w:t>Generated: ' . htmlspecialchars(date('Y-m-d H:i:s')) . '</w:t>
            </w:r>
        </w:p>
        <w:p><w:r><w:br/></w:r></w:p>';

    if (!empty($report_data)) {
        $doc_xml .= '
        <w:tbl>
            <w:tblPr>
                <w:tblW w:w="5000" w:type="auto"/>
                <w:tblBorders>
                    <w:top w:val="single" w:sz="12" w:space="0" w:color="000000"/>
                    <w:left w:val="single" w:sz="12" w:space="0" w:color="000000"/>
                    <w:bottom w:val="single" w:sz="12" w:space="0" w:color="000000"/>
                    <w:right w:val="single" w:sz="12" w:space="0" w:color="000000"/>
                    <w:insideH w:val="single" w:sz="12" w:space="0" w:color="000000"/>
                    <w:insideV w:val="single" w:sz="12" w:space="0" w:color="000000"/>
                </w:tblBorders>
            </w:tblPr>';

        // Header row
        $doc_xml .= '<w:tr><w:trPr><w:trHeight w:val="400" w:type="atLeast"/></w:trPr>';
        
        if ($report_type === 'fines') {
            $headers = ['Name', 'Course Year', 'Total Fines', 'Status'];
        } elseif ($report_type === 'books') {
            $headers = ['Book Title', 'Borrow Count'];
        } else {
            $headers = ['Name', 'Course', 'Time In', 'Time Out', 'Duration', 'Purpose'];
        }

        foreach ($headers as $header) {
            $doc_xml .= '
            <w:tc>
                <w:tcPr><w:shd w:fill="D6A84A"/></w:tcPr>
                <w:p>
                    <w:r>
                        <w:rPr><w:b/></w:rPr>
                        <w:t>' . htmlspecialchars($header) . '</w:t>
                    </w:r>
                </w:p>
            </w:tc>';
        }
        $doc_xml .= '</w:tr>';

        // Data rows
        foreach ($report_data as $row) {
            $doc_xml .= '<w:tr>';
            
            if ($report_type === 'fines') {
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($row['name'] ?? 'N/A') . '</w:t></w:r></w:p></w:tc>';
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($row['course_year'] ?? 'N/A') . '</w:t></w:r></w:p></w:tc>';
                $doc_xml .= '<w:tc><w:p><w:r><w:t>₱' . number_format($row['total_fines'] ?? 0, 2) . '</w:t></w:r></w:p></w:tc>';
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($row['status'] ?? 'N/A') . '</w:t></w:r></w:p></w:tc>';
            } elseif ($report_type === 'books') {
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($row['title'] ?? 'N/A') . '</w:t></w:r></w:p></w:tc>';
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . ($row['borrow_count'] ?? '0') . '</w:t></w:r></w:p></w:tc>';
            } else {
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($row['name'] ?? 'N/A') . '</w:t></w:r></w:p></w:tc>';
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($row['course_year'] ?? 'N/A') . '</w:t></w:r></w:p></w:tc>';
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($row['time_in'] ?? 'N/A') . '</w:t></w:r></w:p></w:tc>';
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($row['time_out'] ?? 'Active') . '</w:t></w:r></w:p></w:tc>';
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . ($row['duration_minutes'] ?? '0') . ' min</w:t></w:r></w:p></w:tc>';
                $doc_xml .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars($row['purpose'] ?? 'N/A') . '</w:t></w:r></w:p></w:tc>';
            }
            
            $doc_xml .= '</w:tr>';
        }
        $doc_xml .= '</w:tbl>';
    }

    $doc_xml .= '
    </w:body>
</w:document>';
    
    file_put_contents($temp_dir . '/word/document.xml', $doc_xml);

    // Create word/_rels/document.xml.rels
    $doc_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>';
    file_put_contents($temp_dir . '/word/_rels/document.xml.rels', $doc_rels);

    // Create ZIP file
    $docx_file = sys_get_temp_dir() . '/report_' . time() . '_' . uniqid() . '.docx';
    $zip = new ZipArchive();
    
    if ($zip->open($docx_file, ZipArchive::CREATE) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($temp_dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $zip->close();
        
        // Cleanup
        array_map('unlink', glob($temp_dir . '/word/_rels/*'));
        array_map('unlink', glob($temp_dir . '/word/*'));
        array_map('unlink', glob($temp_dir . '/_rels/*'));
        array_map('unlink', glob($temp_dir . '/*'));
        @rmdir($temp_dir . '/word/_rels');
        @rmdir($temp_dir . '/word');
        @rmdir($temp_dir . '/_rels');
        @rmdir($temp_dir);
        
        return $docx_file;
    }

    return false;
}

/**
 * Get the latest uploaded template
 */
function get_latest_template() {
    $template_dir = __DIR__ . '/../letterhead/letterhead template/';
    if (!is_dir($template_dir)) {
        return null;
    }

    $files = scandir($template_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($template_dir . $file)) {
            return 'letterhead/letterhead template/' . $file;
        }
    }

    return null;
}

?>