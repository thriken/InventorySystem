// DataTables 通用配置
const defaultDataTableConfig = {
    "language": {
        "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Chinese.json",
        "emptyTable": "暂无数据",
        "loadingRecords": "加载中...",
        "processing": "处理中...",
        "search": "搜索:",
        "lengthMenu": "显示 _MENU_ 条记录",
        "info": "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
        "infoEmpty": "显示第 0 至 0 项结果，共 0 项",
        "infoFiltered": "(由 _MAX_ 项结果过滤)",
        "paginate": {
            "first": "首页",
            "last": "末页",
            "next": "下一页",
            "previous": "上一页"
        }
    },
    "pageLength": 15,
    "lengthMenu": [[15, 30, 50, 100, -1], [15, 30, 50, 100, "全部"]],
    "responsive": true,
    "autoWidth": false,
    "processing": true,
    "serverSide": false,
    "stateSave": true,
    "stateDuration": 60 * 60 * 24, // 24小时
    "columnDefs": [
        { "orderable": false, "targets": "no-sort" },
        { "searchable": false, "targets": "no-search" },
        { "className": "text-center", "targets": "text-center" },
        { "className": "text-right", "targets": "text-right" }
    ],
    "dom": '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip',
    "order": [[ 0, "desc" ]], // 默认按第一列降序排列
    "drawCallback": function(settings) {
        // 重新绑定按钮事件（如果需要）
        bindTableEvents();
    }
};

// 预定义的配置模板
const configTemplates = {
    // 用户管理表格配置
    users: {
        "order": [[ 0, "desc" ]],
        "columnDefs": [
            { "orderable": false, "targets": [6] }, // 操作列不排序
            { "className": "text-center", "targets": [4, 5, 6] } // 状态、创建时间、操作列居中
        ]
    },
    
    // 字典管理表格配置
    dictionary: {
        "order": [[ 1, "asc" ], [ 5, "asc" ]],
        "columnDefs": [
            { "orderable": false, "targets": [8] }, // 操作列不排序
            { "className": "text-center", "targets": [0, 5, 6, 7, 8] } // ID、排序、状态、时间、操作列居中
        ]
    },
    
    // 原片类型表格配置
    glassTypes: {
        "order": [[ 0, "desc" ]],
        "columnDefs": [
            { "orderable": false, "targets": [12] }, // 操作列不排序（第13列，索引12）
            { "className": "text-center", "targets": [0, 11, 12] }, // ID、创建时间、操作列居中
            { "className": "text-right", "targets": [5] } // 厚度列右对齐
        ]
    },
    
    // 基地管理表格配置
    bases: {
        "order": [[ 0, "desc" ]],
        "columnDefs": [
            { "orderable": false, "targets": [4] }, // 操作列不排序
            { "className": "text-center", "targets": [0, 3, 4] } // ID、状态、操作列居中
        ]
    },
    
    // 交易记录表格配置
    transactions: {
        "order": [[ 0, "desc" ]],
        "columnDefs": [
            { "orderable": false, "targets": [8] }, // 操作列不排序
            { "className": "text-center", "targets": [0, 2, 7, 8] }, // ID、类型、时间、操作列居中
            { "className": "text-right", "targets": [5, 6] } // 数量列右对齐
        ]
    },
    
    // 报表数据表格配置
    reports: {
        "order": [[ 0, "desc" ]],
        "columnDefs": [
            { "className": "text-center", "targets": [0, 1, 7] }, // 日期、基地、状态列居中
            { "className": "text-right", "targets": [3, 4, 5, 6] } // 数量列右对齐
        ],
        "footerCallback": function(row, data, start, end, display) {
            // 计算合计
            const api = this.api();
            const intVal = function(i) {
                return typeof i === 'string' ?
                    i.replace(/[\$,]/g, '') * 1 :
                    typeof i === 'number' ? i : 0;
            };
            
            // 计算各列总和
            for (let i = 3; i <= 6; i++) {
                const total = api
                    .column(i, { page: 'current' })
                    .data()
                    .reduce(function(a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);
                
                $(api.column(i).footer()).html(total.toLocaleString());
            }
        }
    }
};

// 初始化 DataTable 的通用函数
function initDataTable(tableId, templateName = null, customConfig = {}) {
    let config = Object.assign({}, defaultDataTableConfig);
    
    // 如果指定了模板，合并模板配置
    if (templateName && configTemplates[templateName]) {
        const templateConfig = configTemplates[templateName];
        
        // 深度合并配置，特别处理 columnDefs 数组
        config = Object.assign(config, templateConfig);
        
        // 如果模板有 columnDefs，需要与默认的 columnDefs 合并而不是覆盖
        if (templateConfig.columnDefs) {
            config.columnDefs = [...defaultDataTableConfig.columnDefs, ...templateConfig.columnDefs];
        }
    }
    
    // 合并自定义配置
    if (customConfig.columnDefs) {
        config.columnDefs = [...config.columnDefs, ...customConfig.columnDefs];
        delete customConfig.columnDefs;
    }
    config = Object.assign(config, customConfig);
    
    return $(tableId).DataTable(config);
}

// 重新绑定表格事件的函数
function bindTableEvents() {
    // 重新绑定删除确认事件
    $('.btn-delete').off('click').on('click', function(e) {
        e.preventDefault();
        if (confirm('确定要删除这条记录吗？此操作不可恢复！')) {
            // 执行删除操作
            const deleteUrl = $(this).attr('href') || $(this).data('url');
            if (deleteUrl) {
                window.location.href = deleteUrl;
            }
        }
    });
    
    // 重新绑定其他按钮事件
    $('.btn-edit').off('click').on('click', function(e) {
        // 编辑按钮事件处理
    });
}

// 导出功能
function addExportButtons(tableId, filename = 'export') {
    const table = $(tableId).DataTable();
    
    // 添加导出按钮到表格上方
    const exportButtons = `
        <div class="export-buttons mb-3">
            <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel('${tableId}', '${filename}')">
                <i class="fas fa-file-excel"></i> 导出Excel
            </button>
            <button type="button" class="btn btn-info btn-sm" onclick="exportToPDF('${tableId}', '${filename}')">
                <i class="fas fa-file-pdf"></i> 导出PDF
            </button>
        </div>
    `;
    
    $(tableId + '_wrapper').prepend(exportButtons);
}

// Excel导出功能
function exportToExcel(tableId, filename) {
    // 确保tableId以#开头
    if (!tableId.startsWith('#')) {
        tableId = '#' + tableId;
    }
    
    const table = $(tableId).DataTable();
    
    // 检查表格是否存在且已初始化
    if (!table || !$.fn.DataTable.isDataTable(tableId)) {
        alert('表格未正确初始化');
        return;
    }
    
    // 获取表格数据
    const data = [];
    const headers = [];
    
    // 获取表头（排除操作列）
    $(tableId + ' thead th').each(function(index) {
        const headerText = $(this).text().trim();
        if (headerText && headerText !== '操作') {
            headers.push(headerText);
        }
    });
    
    // 添加表头到数据数组
    data.push(headers);
    
    // 获取当前显示的数据行
    table.rows({ search: 'applied' }).every(function(rowIdx, tableLoop, rowLoop) {
        const row = [];
        const rowNode = this.node();
        
        // 遍历每个单元格，但排除最后一列（操作列）
        $(rowNode).find('td').each(function(cellIndex) {
            // 只处理非操作列的数据
            if (cellIndex < headers.length) {
                let cellText = $(this).text().trim();
                // 清理多余的空白字符和换行符
                cellText = cellText.replace(/\s+/g, ' ').replace(/\n/g, ' ');
                row.push(cellText);
            }
        });
        
        // 确保行数据长度与表头一致
        if (row.length === headers.length) {
            data.push(row);
        }
    });
    
    // 检查是否有数据
    if (data.length <= 1) {
        alert('没有可导出的数据');
        return;
    }
    
    // 创建CSV内容
    const csvContent = data.map(row => 
        row.map(cell => `"${cell.toString().replace(/"/g, '""')}"`).join(',')
    ).join('\n');
    
    // 创建并下载文件
    const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', (filename || 'export') + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // 清理URL对象
    URL.revokeObjectURL(url);
}

// PDF导出功能
function exportToPDF(tableId, filename) {
    // 确保tableId以#开头
    if (!tableId.startsWith('#')) {
        tableId = '#' + tableId;
    }
    
    const table = $(tableId).DataTable();
    
    // 检查表格是否存在且已初始化
    if (!table || !$.fn.DataTable.isDataTable(tableId)) {
        alert('表格未正确初始化');
        return;
    }
    
    // 获取表格数据
    const headers = [];
    const rows = [];
    
    // 获取表头（排除操作列）
    $(tableId + ' thead th').each(function(index) {
        const headerText = $(this).text().trim();
        if (headerText && headerText !== '操作') {
            headers.push(headerText);
        }
    });
    
    // 获取当前显示的数据行
    table.rows({ search: 'applied' }).every(function(rowIdx, tableLoop, rowLoop) {
        const row = [];
        const rowNode = this.node();
        
        // 遍历每个单元格，但排除最后一列（操作列）
        $(rowNode).find('td').each(function(cellIndex) {
            // 只处理非操作列的数据
            if (cellIndex < headers.length) {
                let cellText = $(this).text().trim();
                // 清理多余的空白字符和换行符
                cellText = cellText.replace(/\s+/g, ' ').replace(/\n/g, ' ');
                row.push(cellText);
            }
        });
        
        // 确保行数据长度与表头一致
        if (row.length === headers.length) {
            rows.push(row);
        }
    });
    
    // 检查是否有数据
    if (rows.length === 0) {
        alert('没有可导出的数据');
        return;
    }
    
    // 创建HTML内容
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>${filename || 'export'}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                tr:nth-child(even) { background-color: #f9f9f9; }
            </style>
        </head>
        <body>
            <h2>${filename || 'export'}</h2>
            <table>
                <thead>
                    <tr>
                        ${headers.map(header => `<th>${header}</th>`).join('')}
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(row => 
                        `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`
                    ).join('')}
                </tbody>
            </table>
        </body>
        </html>
    `;
    
    // 创建并下载文件
    const blob = new Blob([htmlContent], { type: 'text/html;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', (filename || 'export') + '.html');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // 清理URL对象
    URL.revokeObjectURL(url);
}

// 页面加载完成后的初始化
$(document).ready(function() {
    // 自动初始化所有带有 data-table 属性的表格
    $('[data-table]').each(function() {
        const tableId = '#' + $(this).attr('id');
        const template = $(this).data('table');
        const exportName = $(this).data('export') || 'data';
        
        // 初始化表格
        initDataTable(tableId, template);
        
        // 如果需要导出功能
        if ($(this).data('export')) {
            addExportButtons(tableId, exportName);
        }
    });
    
    // 绑定初始事件
    bindTableEvents();
});