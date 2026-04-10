function exportToCSV(data, filename) {
    if (!data || !data.length) {
        alert('暂无数据可导出');
        return;
    }

    const headers = Object.keys(data[0]);
    const csvContent = [
        headers.join(','),
        ...data.map(row => headers.map(header => {
            let cell = row[header] === null || row[header] === undefined ? '' : String(row[header]);
            cell = cell.replace(/"/g, '""');
            if (cell.search(/[",\n]/) >= 0) {
                cell = '"' + cell + '"';
            }
            return cell;
        }).join(','))
    ].join('\n');

    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.csv';
    link.click();
    URL.revokeObjectURL(link.href);
}

function exportToExcel(data, filename, sheetName) {
    if (!data || !data.length) {
        alert('暂无数据可导出');
        return;
    }

    const headers = Object.keys(data[0]);
    let xmlContent = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>';
    xmlContent += '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
    xmlContent += 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    xmlContent += '<Worksheet ss:Name="' + (sheetName || 'Sheet1') + '"><Table>';

    xmlContent += '<Row>';
    headers.forEach(header => {
        xmlContent += '<Cell><Data ss:Type="String">' + escapeXml(String(header)) + '</Data></Cell>';
    });
    xmlContent += '</Row>';

    data.forEach(row => {
        xmlContent += '<Row>';
        headers.forEach(header => {
            let cell = row[header] === null || row[header] === undefined ? '' : String(row[header]);
            cell = escapeXml(cell);
            const dataType = isNaN(cell) || cell === '' ? 'String' : 'Number';
            xmlContent += '<Cell><Data ss:Type="' + dataType + '">' + cell + '</Data></Cell>';
        });
        xmlContent += '</Row>';
    });

    xmlContent += '</Table></Worksheet></Workbook>';

    const blob = new Blob([xmlContent], { type: 'application/vnd.ms-excel' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.xls';
    link.click();
    URL.revokeObjectURL(link.href);
}

function escapeXml(str) {
    return str.replace(/[<>&'"]/g, function(c) {
        switch(c) {
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '&': return '&amp;';
            case "'": return '&apos;';
            case '"': return '&quot;';
        }
    });
}

function formatDateForExport(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toISOString().split('T')[0];
}

function formatMoneyForExport(amount) {
    if (amount === null || amount === undefined) return '0';
    return parseFloat(amount).toFixed(2);
}
