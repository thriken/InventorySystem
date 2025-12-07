// DataTables 通用配置
const defaultDataTableConfig = {
    "language": {
        "url": "//cdn.datatables.net/plug-ins/2.3.5/i18n/zh.json",
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
        // 安全地重新绑定按钮事件（仅在有操作列时）
        safeBindTableEvents();
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
            const api = this.api();
            const intVal = function(i) {
                return typeof i === 'string' ?
                    i.replace(/[\$,]/g, '') * 1 :
                    typeof i === 'number' ? i : 0;
            };
            
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
    },
    
    // 库存查询表格配置
    inventory: {
        "order": [[ 9, "desc" ]], // 按入库日期降序排列
        "dom": 'lBfrtip',// 包含分页控件和导出按钮
        "buttons": [
            {
                "extend": 'excel',
                "text": '导出 Excel',
                "className": 'btn btn-success btn-sm',
                "filename": function() {
                    const date = new Date().toISOString().slice(0, 10);
                    return '库存数据_' + date;
                },
                "exportOptions": {
                    "columns": [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10] // 排除状态列
                }
            }
        ],
        "columnDefs": [
            { "className": "text-center", "targets": [0, 1, 2, 5, 6, 7, 8, 11] }, // 包号、名称、简称、颜色、厚度、库位、基地、状态列居中
            { "className": "text-right", "targets": [4, 5] }, // 片数、面积右对齐
            { "type": "date", "targets": [10] } // 日期列
        ],
        "footerCallback": function(row, data, start, end, display) {
            const api = this.api();
            
            // 移除非数字字符的函数
            const cleanNumber = function(i) {
                return typeof i === 'string' ?
                    parseFloat(i.replace(/[^\d.-]/g, '')) :
                    typeof i === 'number' ? i : 0;
            };
            
            // 计算片数总和（第4列，索引4）
            const totalPieces = api
                .column(4, { page: 'current' }) // 只计算当前页
                .data()
                .reduce(function(a, b) {
                    return cleanNumber(a) + cleanNumber(b);
                }, 0);
            
            // 计算面积总和（第5列，索引5）
            const totalArea = api
                .column(5, { page: 'current' }) // 只计算当前页
                .data()
                .reduce(function(a, b) {
                    return cleanNumber(a) + cleanNumber(b);
                }, 0);
            
            // 更新页脚显示
            $(api.column(4).footer()).html(totalPieces.toLocaleString());
            $(api.column(5).footer()).html(totalArea.toFixed(4));
        }
    },
    
    // 加工库存表格配置
    processing: {
        "order": [[ 10, "desc" ]], // 按使用时间降序排列
        "dom": 'lBfrtip', // 包含分页控件和导出按钮
        "buttons": [
            {
                "extend": 'excel',
                "text": '导出 Excel',
                "className": 'btn btn-success btn-sm',
                "filename": function() {
                    const date = new Date().toISOString().slice(0, 10);
                    return '加工区库存_' + date;
                },
                "exportOptions": {
                    "columns": [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10] // 排除状态列
                }
            }
        ],
        "columnDefs": [
            { "className": "text-center", "targets": [0, 1, 2, 5, 6, 7, 10, 11] }, // 包号、名称、简称、颜色、厚度、库位、基地、状态列居中
            { "className": "text-right", "targets": [4] }, // 使用片数右对齐
            { "type": "date", "targets": [10] } // 使用时间列
        ]
    },
    
    // 库位架管理表格配置
    racks: {
        "order": [[ 0, "asc" ]], // 按ID升序排列
        "columnDefs": [
            { "orderable": false, "targets": [8] }, // 操作列不排序
            { "className": "text-center", "targets": [0, 5, 6, 7, 8] }, // ID、状态、包数量、创建时间、操作列居中
            { "className": "no-sort", "targets": [8] } // 操作列添加no-sort类
        ]
    }
};

// 初始化 DataTable 的通用函数
function initDataTable(tableId, templateName = null, customConfig = {}) {
    // 检查是否已经初始化过DataTable，避免重复初始化
    if ($.fn.dataTable.isDataTable(tableId)) {
        $(tableId).DataTable().destroy();
    }
    
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
    
    // 添加防重复初始化的保护选项
    config = Object.assign(config, customConfig, {
        retrieve: true,
        destroy: true
    });
    
    return $(tableId).DataTable(config);
}

// 安全地重新绑定表格事件的函数（避免在 viewer 页面出错）
function safeBindTableEvents() {
    // 只在有删除按钮时绑定删除事件
    const $deleteBtns = $('.btn-delete');
    if ($deleteBtns.length > 0) {
        $deleteBtns.off('click').on('click', function(e) {
            e.preventDefault();
            if (confirm('确定要删除这条记录吗？此操作不可恢复！')) {
                // 执行删除操作
                const deleteUrl = $(this).attr('href') || $(this).data('url');
                if (deleteUrl) {
                    window.location.href = deleteUrl;
                }
            }
        });
    }
    
    // 只在有编辑按钮时绑定编辑事件
    const $editBtns = $('.btn-edit');
    if ($editBtns.length > 0) {
        $editBtns.off('click').on('click', function(e) {
            // 编辑按钮事件处理
        });
    }
}

// 重新绑定表格事件的函数（保留向后兼容）
function bindTableEvents() {
    safeBindTableEvents();
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
        
        // 初始化表格（包含防重复初始化保护）
        initDataTable(tableId, template);
        
        // 如果需要导出功能（兼容旧配置）
        if ($(this).data('export')) {
            addExportButtons(tableId, exportName);
        }
    });
    
    // 绑定初始事件（安全版本）
    safeBindTableEvents();
    
    // 处理 URL 搜索参数，自动应用到 DataTable 搜索框
    const urlParams = new URLSearchParams(window.location.search);
    const searchValue = urlParams.get('search');
    
    if (searchValue) {
        // 等待表格初始化完成后再应用搜索
        setTimeout(function() {
            $('[data-table]').each(function() {
                const tableId = '#' + $(this).attr('id');
                if ($.fn.dataTable.isDataTable(tableId)) {
                    $(tableId).DataTable().search(searchValue).draw();
                }
            });
        }, 100);
    }
});