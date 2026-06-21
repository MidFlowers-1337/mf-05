# 👗 礼服租赁管理系统

基于 PHP + Symfony 6.4 + SQLite (Doctrine) 构建的礼服租赁店管理系统。

## ✨ 功能特性

- **服装登记**：款名、尺码、颜色、买价、押金、日租金、现状照片
- **出租管理**：按日期出租、客户信息录入、押金收取、自动生成租单
- **归还检查**：检查损坏、逐项记录扣款、押金自动结算退还
- **逾期催还**：自动标记逾期订单、高亮显示逾期天数
- **清洗排期**：归还后自动安排清洗、清洗流程跟踪（待洗→清洗中→完成）
- **历史记录**：每件服装的租赁历史、损坏记录、清洗记录全可查
- **统计报表**：月度出租率、租金收入、损坏扣款、最抢手服装 TOP 10

## 🏗️ 架构说明

业务逻辑按职责分离到不同 Service，互不堆在一起：

| 服务 | 职责 | 文件 |
|------|------|------|
| RentalService | 出租管理（创建租单、客户查询/创建、逾期状态更新） | [RentalService.php](src/Service/RentalService.php) |
| ReturnService | 归还管理（损坏检查、押金扣除、结单） | [ReturnService.php](src/Service/ReturnService.php) |
| CleaningService | 清洗排期（安排清洗、开始、完成、损坏修复后置清洗） | [CleaningService.php](src/Service/CleaningService.php) |
| StatsService | 统计报表（月度出租率、最抢手服装） | [StatsService.php](src/Service/StatsService.php) |

数据库使用 Doctrine ORM 管理 SQLite，未引入额外数据库层。

## 🚀 快速开始

### 环境要求
- PHP >= 8.1
- Composer
- Symfony CLI（可选，推荐）

### 安装步骤

```bash
# 1. 进入项目目录
cd d:\mf-05

# 2. 安装依赖
composer install

# 3. 初始化数据库（自动创建 SQLite 文件 var/data.db）
php bin/console doctrine:database:create
php bin/console doctrine:schema:create

# 4. 启动开发服务器（端口 7894）
symfony server:start
```

启动后访问：**http://localhost:7894**

## 📖 使用指南

### 操作一：登记一件新衣服

1. 点击顶部导航栏「服装管理」
2. 点击右上角「+ 登记新服装」按钮
3. 填写：
   - **款名**：例如「鱼尾婚纱A款」
   - **尺码**：S/M/L/XL 或具体号型
   - **颜色**：白色、酒红色等
   - **买价**：服装采购成本
   - **押金**：出租时收取的押金金额
   - **日租金**：每天的租金
   - **状态**：默认「可出租」
   - **现状照片**：上传图片（可选，支持 JPG/PNG/GIF/WEBP，最大 5MB）
4. 点击「保存登记」

### 操作二：把衣服租出去

1. 在「服装管理」列表中找到状态为「可出租」的衣服
2. 点击右侧「出租」按钮（或进入详情页后点「出租」）
3. 填写客户信息：
   - **客户姓名**（必填）
   - **联系电话**（推荐填写，方便后续催还）
   - 身份证号、住址（可选）
4. 选择租赁日期：
   - **出租日期**：默认今天
   - **应还日期**：默认 3 天后
5. 点击「确认出租」
6. 系统自动生成租单号，收取押金，服装状态变为「已租出」

### 操作三：客人归还，检查损坏并扣押金

1. 点击顶部导航栏「归还检查」，或在租单详情页点「办理归还」
2. 确认归还日期（默认今天）
3. 检查服装：
   - 如果有损坏，点击「+ 添加一项损坏」
   - 填写**损坏描述**（如：裙摆破洞、领口污渍）和**扣款金额**
   - 可添加多条损坏记录，页面底部实时显示「预计退还」金额
4. 默认勾选「需要清洗」，归还后自动排进清洗队列
5. 点击「确认归还」
6. 系统提示：押金 ¥XXX，扣款 ¥XXX，退还 ¥XXX
7. 服装状态：
   - 无损坏 → 进入「清洗中」
   - 有损坏 → 标记为「损坏待修」

### 其他操作

- **逾期催还**：点击导航栏「逾期催还」查看所有超期未还的服装，直接联系客户或办理归还
- **清洗排期**：点击导航栏「清洗排期」→ 点「开始清洗」→ 洗完后点「清洗完成」，服装自动变回可出租状态
- **服装历史**：进入任意服装详情页，查看租赁历史、损坏记录、清洗记录
- **统计报表**：点击「统计报表」，选择年月查看出租率、收入和最抢手款式

## 🧪 运行测试

归还扣押金逻辑已编写单元测试，共 12 个测试用例：

```bash
php bin/phpunit tests/Service/ReturnServiceTest.php
```

覆盖场景：
- 无损坏正常归还（全额退押金 + 自动排清洗）
- 有损坏归还（扣除对应押金 + 标记损坏状态）
- 损坏扣款超过押金（抛出异常拒绝操作）
- 已结单租单重复操作（拒绝）
- 归还日期早于出租日期（拒绝）
- 不需要清洗的归还（服装直接变为可出租）
- 损坏金额合计 / 退款金额计算
- 结单操作验证
- 空损坏记录自动忽略

## 📁 项目文件清单

```
d:\mf-05\
├── bin/
│   └── console                         # Symfony 控制台入口
├── config/
│   ├── bundles.php                     # 注册的 Bundle
│   ├── routes.yaml                     # 路由配置
│   ├── services.yaml                   # 服务容器配置
│   └── packages/
│       ├── doctrine.yaml               # Doctrine + SQLite 配置
│       ├── framework.yaml              # 框架核心配置
│       └── twig.yaml                   # Twig 模板配置
├── public/
│   ├── index.php                       # 前端控制器
│   └── uploads/                        # 服装照片上传目录
├── src/
│   ├── Kernel.php                      # Symfony 内核
│   ├── Controller/                     # 控制器层
│   │   ├── HomeController.php          # 首页仪表盘
│   │   ├── DressController.php         # 服装管理
│   │   ├── RentalController.php        # 出租管理
│   │   ├── ReturnController.php        # 归还检查
│   │   ├── CleaningController.php      # 清洗排期
│   │   └── StatsController.php         # 统计报表
│   ├── Entity/                         # Doctrine 实体
│   │   ├── Dress.php                   # 服装
│   │   ├── Customer.php                # 客户
│   │   ├── Rental.php                  # 租单
│   │   ├── DamageRecord.php            # 损坏记录
│   │   └── CleaningRecord.php          # 清洗记录
│   ├── Repository/                     # 自定义 Repository
│   │   ├── DressRepository.php
│   │   ├── CustomerRepository.php
│   │   ├── RentalRepository.php
│   │   ├── DamageRecordRepository.php
│   │   └── CleaningRecordRepository.php
│   ├── Service/                        # 业务逻辑层（按职责分离）
│   │   ├── RentalService.php           # 出租业务
│   │   ├── ReturnService.php           # 归还+押金扣款业务
│   │   ├── CleaningService.php         # 清洗排期业务
│   │   └── StatsService.php            # 统计计算
│   └── Form/
│       └── DressType.php               # 服装表单类型
├── templates/                          # Twig 模板（不引入前端框架）
│   ├── base.html.twig                  # 基础布局
│   ├── home/
│   │   └── index.html.twig             # 首页仪表盘
│   ├── dress/
│   │   ├── index.html.twig             # 服装列表
│   │   ├── new.html.twig               # 新增服装
│   │   ├── show.html.twig              # 服装详情+历史
│   │   └── edit.html.twig              # 编辑服装
│   ├── rental/
│   │   ├── index.html.twig             # 租单列表
│   │   ├── overdue.html.twig           # 逾期催还
│   │   ├── new.html.twig               # 新建租单
│   │   └── show.html.twig              # 租单详情
│   ├── return/
│   │   ├── index.html.twig             # 归还待办
│   │   └── process.html.twig           # 归还检查+扣款
│   ├── cleaning/
│   │   └── index.html.twig             # 清洗排期
│   └── stats/
│       └── index.html.twig             # 统计报表
├── tests/
│   ├── bootstrap.php                   # 测试引导
│   └── Service/
│       └── ReturnServiceTest.php       # 归还扣押金单元测试（12用例）
├── var/                                # 缓存 + SQLite 数据库文件
├── .env                                # 环境变量（含 DATABASE_URL）
├── .gitignore
├── .symfony.local.yaml                 # Symfony Server 端口配置 7894
├── composer.json
├── phpunit.xml.dist                    # PHPUnit 配置
└── README.md                           # 本文档
```

## 📌 访问入口

启动命令：`symfony server:start`

| 功能 | URL |
|------|-----|
| 首页仪表盘 | http://localhost:7894/ |
| 服装管理 | http://localhost:7894/dress/ |
| 出租管理 | http://localhost:7894/rental/ |
| 归还检查 | http://localhost:7894/return/ |
| 逾期催还 | http://localhost:7894/rental/overdue |
| 清洗排期 | http://localhost:7894/cleaning/ |
| 统计报表 | http://localhost:7894/stats/ |
